<?php

/**
 * Critical CSS extractor.
 *
 * Picks the subset of a stylesheet that an above-the-fold render needs and
 * caches the result per route so a `@criticalCss` directive in the head can
 * inline it without re-parsing on every request.
 *
 * The extractor uses a rule-based heuristic rather than a headless browser:
 * we keep `@font-face` and `@keyframes` at-rules (they're nearly always
 * critical), drop `@media (min-width: …)` queries that target viewports
 * larger than the configured critical viewport, and accept rules whose
 * selectors match a curated list of above-the-fold patterns (resets, layout
 * shell elements, fold markers like `.hero` / `[data-critical]`). The
 * heuristic is intentionally conservative — false positives bloat the inline
 * style block but don't break the page, while false negatives would leave
 * the page rendering against a partial stylesheet.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Css;

use Illuminate\Contracts\Cache\Repository;
use RuntimeException;
use Throwable;

/**
 * Critical CSS extractor class.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @since      1.0.0
 */
class CriticalCssExtractor
{
	/**
	 * Default critical viewport width when none is configured.
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	public const DEFAULT_WIDTH = 1300;

	/**
	 * Default critical viewport height when none is configured.
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	public const DEFAULT_HEIGHT = 900;

	/**
	 * Default cache namespace for stored critical CSS.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public const CACHE_KEY_PREFIX = 'performance:critical-css:';

	/**
	 * Fallback route key used when a caller doesn't supply one.
	 *
	 * Consumed by both the `@criticalCss` Blade directive compiler (when the
	 * request route is unnamed) and `generate()` (when a registered route
	 * has no specific sources). Exposing this as a constant prevents the two
	 * call sites from drifting apart if the sentinel ever changes.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public const DEFAULT_ROUTE = 'default';

	/**
	 * Curated selectors treated as above-the-fold by default.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public const DEFAULT_CRITICAL_SELECTORS = [
		'*',
		':root',
		'html',
		'body',
		'header',
		'nav',
		'main',
		'.container',
		'.wrapper',
		'.layout',
		'.hero',
		'.above-fold',
		'.site-header',
		'.site-nav',
		'[data-critical]',
		'h1',
		'h2',
	];

	/**
	 * At-rules whose blocks are always preserved.
	 *
	 * Includes both block-style at-rules (`@font-face { ... }`,
	 * `@keyframes ...`) and body-less at-rules that terminate with `;`
	 * (`@import url(...)`, `@charset "UTF-8"`, `@namespace ...`). All of
	 * these are stylesheet-level declarations that affect the entire
	 * cascade, so dropping them from the critical block would silently
	 * break above-the-fold rendering.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	protected const ALWAYS_PRESERVE_AT_RULES = [
		'font-face',
		'keyframes',
		'supports',
		'page',
		'import',
		'charset',
		'namespace',
		'layer',
	];

	/**
	 * Source CSS bundles registered per route.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, array<int, string>>
	 */
	protected array $sources = [];

	/**
	 * Optional cache override pinned at construction time.
	 *
	 * @since 1.0.0
	 *
	 * @var Repository|null
	 */
	protected ?Repository $cache;

	/**
	 * Creates a new extractor.
	 *
	 * @since 1.0.0
	 *
	 * @param Repository|null $cache Optional cache repository override.
	 */
	public function __construct( ?Repository $cache = null )
	{
		$this->cache = $cache;
	}

	/**
	 * Registers a CSS source (path or raw content) for the given route.
	 *
	 * Use the literal `default` route name to register CSS that applies to
	 * every route that doesn't have its own registration. Multiple sources
	 * registered against the same route are concatenated before extraction.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $route             Route name or path key.
	 * @param  string $cssPathOrContent  Absolute path to a CSS file, or raw CSS content.
	 *
	 * @return $this
	 */
	public function registerSource( string $route, string $cssPathOrContent ): self
	{
		$this->sources[ $route ] ??= [];
		$this->sources[ $route ][]   = $cssPathOrContent;

		return $this;
	}

	/**
	 * Returns every CSS source registered against the given route.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $route Route name or path key.
	 *
	 * @return array<int, string>
	 */
	public function sourcesFor( string $route ): array
	{
		return $this->sources[ $route ] ?? [];
	}

	/**
	 * Returns every route name that has at least one registered source.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	public function registeredRoutes(): array
	{
		return array_values( array_map( 'strval', array_keys( $this->sources ) ) );
	}

	/**
	 * Extracts critical rules from the given raw CSS.
	 *
	 * Returns the subset of rules whose selectors match the critical-selector
	 * heuristic plus every always-preserved at-rule. `@media (min-width: …)`
	 * blocks that target viewports larger than the critical width are dropped
	 * wholesale.
	 *
	 * @since 1.0.0
	 *
	 * @param  string   $css    Raw CSS content.
	 * @param  int|null $width  Critical viewport width (defaults to config).
	 * @param  int|null $height Critical viewport height (defaults to config).
	 *
	 * @return string
	 */
	public function extract( string $css, ?int $width = null, ?int $height = null ): string
	{
		$width ??= $this->configuredWidth();
		// The height parameter is forwarded for forward-compatibility with a
		// future viewport-aware sampling backend (e.g. a Playwright bridge).
		// Today the heuristic only consults width because the only
		// dimension-driven decision is "drop large `min-width` queries". We
		// still surface `$height` in the public signature so callers don't
		// have to rewrite their bindings when that bridge lands.
		unset( $height );

		if ( '' === trim( $css ) ) {
			return '';
		}

		$css        = $this->stripComments( $css );
		$tokens     = $this->tokenizeRules( $css );
		$selectors  = $this->criticalSelectors();
		$output     = [];

		foreach ( $tokens as $token ) {
			$preserved = $this->preserveToken( $token, $selectors, $width );

			if ( null !== $preserved ) {
				$output[] = $preserved;
			}
		}

		return trim( implode( "\n", $output ) );
	}

	/**
	 * Returns the cached critical CSS for the given route, generating it on miss.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $route Route name or path key.
	 *
	 * @return string
	 */
	public function forRoute( string $route ): string
	{
		$store = $this->cacheRepository();

		if ( null === $store || ! $this->cachingEnabled() ) {
			return $this->generate( $route );
		}

		return (string) $store->rememberForever(
			self::CACHE_KEY_PREFIX . $route,
			fn (): string => $this->generate( $route ),
		);
	}

	/**
	 * Generates critical CSS for the given route without consulting the cache.
	 *
	 * Concatenates every source registered for the route (and falls through to
	 * the `default` route when none are registered) before running the rule
	 * extractor.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $route Route name or path key.
	 *
	 * @return string
	 */
	public function generate( string $route ): string
	{
		$sources = $this->sources[ $route ]
			?? $this->sources[ self::DEFAULT_ROUTE ]
			?? [];

		if ( empty( $sources ) ) {
			return '';
		}

		$bundles = [];

		foreach ( $sources as $source ) {
			$bundles[] = $this->readSource( $source );
		}

		return $this->extract( implode( "\n", $bundles ) );
	}

	/**
	 * Returns the inline `<style>` block for the given route.
	 *
	 * Returns an empty string when no critical CSS was produced so callers can
	 * unconditionally inject the result into the document head.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $route Route name or path key.
	 *
	 * @return string
	 */
	public function inlineFor( string $route ): string
	{
		$css = $this->forRoute( $route );

		if ( '' === $css ) {
			return '';
		}

		return '<style data-critical="' . $this->escape( $route ) . '">' . $css . '</style>';
	}

	/**
	 * Clears the cached critical CSS for a route, or every route when null.
	 *
	 * @since 1.0.0
	 *
	 * @param  string|null $route Route name or null for "every cached route".
	 *
	 * @return void
	 */
	public function clearCache( ?string $route = null ): void
	{
		$store = $this->cacheRepository();

		if ( null === $store ) {
			return;
		}

		if ( null === $route ) {
			foreach ( array_keys( $this->sources ) as $registered ) {
				$store->forget( self::CACHE_KEY_PREFIX . $registered );
			}

			return;
		}

		$store->forget( self::CACHE_KEY_PREFIX . $route );
	}

	/**
	 * Reports whether caching is enabled in the package config.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function cachingEnabled(): bool
	{
		return (bool) config( 'artisanpack.performance.css.critical.cache', true );
	}

	/**
	 * Reads a source string as a CSS file path or returns it as raw content.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $source Source string (path or content).
	 *
	 * @throws RuntimeException When the source looks like a path but cannot be read.
	 *
	 * @return string
	 */
	protected function readSource( string $source ): string
	{
		// Treat anything that looks like CSS (contains `{`) as raw content. A
		// bare path can never contain `{` (it would be an invalid filename on
		// every supported filesystem) so the discriminator is reliable.
		if ( false !== strpos( $source, '{' ) ) {
			return $source;
		}

		if ( ! is_file( $source ) ) {
			throw new RuntimeException( "Critical CSS source is not readable: {$source}" );
		}

		$contents = file_get_contents( $source );

		if ( false === $contents ) {
			throw new RuntimeException( "Failed to read critical CSS source: {$source}" );
		}

		return $contents;
	}

	/**
	 * Decides whether a tokenized rule should be preserved.
	 *
	 * @since 1.0.0
	 *
	 * @param  array{type: string, body: string, query?: string, selectors?: string, declarations?: string} $token       Tokenized rule.
	 * @param  array<int, string>                                                                            $selectors  Critical selector patterns.
	 * @param  int                                                                                           $width      Critical viewport width.
	 *
	 * @return string|null Preserved CSS block, or null when the token should be dropped.
	 */
	protected function preserveToken( array $token, array $selectors, int $width ): ?string
	{
		if ( 'at-rule' === $token['type'] ) {
			return $this->preserveAtRule( $token, $selectors, $width );
		}

		if ( 'rule' === $token['type'] ) {
			return $this->preserveRule( $token, $selectors );
		}

		return null;
	}

	/**
	 * Preserves an at-rule when it is in the always-preserve list or the inner
	 * rules survive critical-selector filtering.
	 *
	 * @since 1.0.0
	 *
	 * @param  array{type: string, body: string, query?: string} $token     Tokenized at-rule.
	 * @param  array<int, string>                                $selectors Critical selector patterns.
	 * @param  int                                               $width     Critical viewport width.
	 *
	 * @return string|null
	 */
	protected function preserveAtRule( array $token, array $selectors, int $width ): ?string
	{
		$query = trim( (string) ( $token['query'] ?? '' ) );

		$matches = [];

		if ( 1 === preg_match( '/^@([a-z-]+)/i', $query, $matches ) ) {
			$name = strtolower( $matches[1] );

			if ( in_array( $name, self::ALWAYS_PRESERVE_AT_RULES, true ) ) {
				// Body-less at-rules (`@import url(...)`, `@charset "UTF-8"`,
				// `@namespace ...`) terminate with `;` — the tokenizer stores
				// them with `body === ''`. Emit them in their declaration
				// form rather than as empty blocks.
				if ( '' === $token['body'] ) {
					return rtrim( $query, ';' ) . ';';
				}

				return $query . ' {' . $token['body'] . '}';
			}

			if ( 'media' === $name && ! $this->mediaQueryAppliesToCritical( $query, $width ) ) {
				return null;
			}
		}

		$innerTokens    = $this->tokenizeRules( $token['body'] );
		$innerPreserved = [];

		foreach ( $innerTokens as $inner ) {
			$preserved = $this->preserveToken( $inner, $selectors, $width );

			if ( null !== $preserved ) {
				$innerPreserved[] = $preserved;
			}
		}

		if ( empty( $innerPreserved ) ) {
			return null;
		}

		return $query . ' {' . "\n" . implode( "\n", $innerPreserved ) . "\n" . '}';
	}

	/**
	 * Preserves a flat rule when any of its selectors matches the critical list.
	 *
	 * @since 1.0.0
	 *
	 * @param  array{type: string, selectors?: string, declarations?: string} $token     Tokenized rule.
	 * @param  array<int, string>                                             $selectors Critical selector patterns.
	 *
	 * @return string|null
	 */
	protected function preserveRule( array $token, array $selectors ): ?string
	{
		$selectorText = trim( (string) ( $token['selectors'] ?? '' ) );

		if ( '' === $selectorText ) {
			return null;
		}

		$selectorParts = array_map( 'trim', explode( ',', $selectorText ) );
		$kept          = [];

		foreach ( $selectorParts as $selector ) {
			if ( $this->isCriticalSelector( $selector, $selectors ) ) {
				$kept[] = $selector;
			}
		}

		if ( empty( $kept ) ) {
			return null;
		}

		$declarations = trim( (string) ( $token['declarations'] ?? '' ) );

		return implode( ', ', $kept ) . ' {' . $declarations . '}';
	}

	/**
	 * Reports whether the given selector should be treated as critical.
	 *
	 * Matches when the selector starts with, equals, or contains any of the
	 * critical-selector patterns as a standalone token (so `.hero` matches
	 * `.hero .title` and `.hero` but not `.heroic`).
	 *
	 * @since 1.0.0
	 *
	 * @param  string             $selector Selector to test.
	 * @param  array<int, string> $patterns Critical selector patterns.
	 *
	 * @return bool
	 */
	protected function isCriticalSelector( string $selector, array $patterns ): bool
	{
		foreach ( $patterns as $pattern ) {
			if ( '' === $pattern ) {
				continue;
			}

			if ( $selector === $pattern ) {
				return true;
			}

			// `*` is the universal selector — match only when it appears as a
			// standalone token. A naive `str_contains($selector, '*')` would
			// also fire on attribute substring selectors like
			// `[data-name*="footer"]`, sweeping clearly below-the-fold rules
			// into the critical block. Restrict to the bare universal token
			// or the `* + *` / `* > *` selector-of-selectors shapes by
			// requiring `*` to sit at a combinator/edge boundary, and
			// `continue` rather than falling through to selectorContainsToken
			// (whose own fallback would re-introduce the same false positive).
			if ( '*' === $pattern ) {
				if ( 1 === preg_match( '/(?:^|[\s>+~,])\*(?:[\s>+~,]|$)/', $selector ) ) {
					return true;
				}

				continue;
			}

			if ( $this->selectorContainsToken( $selector, $pattern ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Reports whether the selector contains the pattern as a complete token.
	 *
	 * "Complete token" means the pattern is bounded by start/end of string or
	 * by a CSS combinator (` `, `>`, `+`, `~`, `,`) so `.hero` matches
	 * `.hero .title` and `body.has-hero` but not `.heroic`.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $selector Selector to test.
	 * @param  string $pattern  Pattern fragment.
	 *
	 * @return bool
	 */
	protected function selectorContainsToken( string $selector, string $pattern ): bool
	{
		// Identifier-like patterns (h1, body, header, html) must be bounded by
		// combinators/edges so the pattern `body` doesn't accept `body-text`.
		// Class- and ID-like patterns (`.hero`, `#main`) also need a trailing
		// boundary so `.hero` doesn't match `.heroic` — the leading `.`/`#` is
		// only a start-of-token marker, the end is still arbitrary. Attribute
		// selectors (`[data-critical]`) are self-bounded by the brackets.
		$escaped       = preg_quote( $pattern, '/' );
		$leadBoundary  = '(?:^|[\s>+~,])';
		$trailBoundary = '(?:[\s>+~,:.\[]|$)';

		if ( 1 === preg_match( '/^[a-z][a-z0-9_-]*$/i', $pattern ) ) {
			return 1 === preg_match( '/' . $leadBoundary . $escaped . $trailBoundary . '/i', $selector );
		}

		if ( 1 === preg_match( '/^[.#][a-z][a-z0-9_-]*$/i', $pattern ) ) {
			return 1 === preg_match( '/' . $escaped . $trailBoundary . '/i', $selector );
		}

		return str_contains( $selector, $pattern );
	}

	/**
	 * Reports whether the given `@media` query applies to the critical viewport.
	 *
	 * Drops queries that target only viewports larger than the critical width
	 * (`min-width: 1400px` for a critical width of 1300). Queries we can't
	 * parse with confidence are kept so we never silently drop something that
	 * matters.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $query Full `@media …` query string.
	 * @param  int    $width Critical viewport width.
	 *
	 * @return bool
	 */
	protected function mediaQueryAppliesToCritical( string $query, int $width ): bool
	{
		$matches = [];

		if ( 1 !== preg_match_all( '/min-width\s*:\s*(\d+)/i', $query, $matches ) ) {
			return true;
		}

		foreach ( $matches[1] as $minWidth ) {
			if ( (int) $minWidth <= $width ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns the configured critical viewport width.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	protected function configuredWidth(): int
	{
		return (int) config( 'artisanpack.performance.css.critical.width', self::DEFAULT_WIDTH );
	}

	/**
	 * Returns the merged critical-selector list (defaults plus config).
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	protected function criticalSelectors(): array
	{
		$configured = (array) config( 'artisanpack.performance.css.critical.selectors', [] );
		$selectors  = array_merge( self::DEFAULT_CRITICAL_SELECTORS, $configured );

		return array_values( array_unique( array_filter( array_map( 'strval', $selectors ) ) ) );
	}

	/**
	 * Strips CSS block comments.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $css Raw CSS.
	 *
	 * @return string
	 */
	protected function stripComments( string $css ): string
	{
		return preg_replace( '#/\*[\s\S]*?\*/#', '', $css ) ?? $css;
	}

	/**
	 * Tokenizes CSS into a flat list of `rule` and `at-rule` entries.
	 *
	 * Hand-rolled to avoid a CSS parser dependency. Supports nested at-rules
	 * (e.g. `@media { .foo {} }`) by walking the source character-by-character
	 * and tracking brace depth — the body of an at-rule is the substring
	 * between its first `{` and the matching `}`.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $css Raw CSS.
	 *
	 * @return array<int, array{type: string, body: string, query?: string, selectors?: string, declarations?: string}>
	 */
	protected function tokenizeRules( string $css ): array
	{
		$tokens   = [];
		$cursor   = 0;
		$length   = strlen( $css );
		$prefix   = '';

		while ( $cursor < $length ) {
			$char = $css[ $cursor ];

			if ( '{' === $char ) {
				$body   = $this->captureBalancedBody( $css, $cursor, $length );
				$head   = trim( $prefix );
				$prefix = '';

				if ( '' === $head ) {
					continue;
				}

				if ( '@' === $head[0] ) {
					$tokens[] = [
						'type'  => 'at-rule',
						'query' => $head,
						'body'  => $body,
					];
				} else {
					$tokens[] = [
						'type'         => 'rule',
						'selectors'    => $head,
						'declarations' => $body,
						'body'         => $body,
					];
				}

				continue;
			}

			if ( ';' === $char ) {
				$head = trim( $prefix );

				// Body-less at-rules (`@import url(...)`, `@charset "UTF-8"`,
				// `@namespace ...`) terminate with `;` instead of `{ ... }`.
				// Emit them as standalone at-rules so the `;` doesn't pollute
				// the head of the next rule that follows. Falling through here
				// would concatenate `@import url(x.css)` with the next
				// selector and yield invalid CSS.
				if ( '' !== $head && '@' === $head[0] ) {
					$tokens[] = [
						'type'  => 'at-rule',
						'query' => $head,
						'body'  => '',
					];
				}

				$cursor++;
				$prefix = '';
				continue;
			}

			$prefix .= $char;
			$cursor++;
		}

		return $tokens;
	}

	/**
	 * Captures the body of a brace-balanced block starting at the current cursor.
	 *
	 * Assumes `$css[$cursor]` is `{`. Advances `$cursor` to one past the
	 * matching `}` and returns the inner contents. Falls back to capturing
	 * to end-of-string when no matching brace exists (malformed CSS).
	 *
	 * @since 1.0.0
	 *
	 * @param  string $css    Raw CSS.
	 * @param  int    $cursor Reference to the cursor position.
	 * @param  int    $length Source length cache.
	 *
	 * @return string
	 */
	protected function captureBalancedBody( string $css, int &$cursor, int $length ): string
	{
		$depth = 0;
		$start = $cursor + 1;

		while ( $cursor < $length ) {
			$char = $css[ $cursor ];

			if ( '{' === $char ) {
				$depth++;
			} elseif ( '}' === $char ) {
				$depth--;

				if ( 0 === $depth ) {
					$body = substr( $css, $start, $cursor - $start );
					$cursor++;

					return $body;
				}
			}

			$cursor++;
		}

		return substr( $css, $start );
	}

	/**
	 * Returns the cache repository the extractor should use.
	 *
	 * @since 1.0.0
	 *
	 * @return Repository|null
	 */
	protected function cacheRepository(): ?Repository
	{
		if ( null !== $this->cache ) {
			return $this->cache;
		}

		if ( ! function_exists( 'cache' ) ) {
			return null;
		}

		try {
			return cache()->store();
		} catch ( Throwable ) {
			return null;
		}
	}

	/**
	 * HTML-escapes the given value for safe interpolation into attribute syntax.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $value Caller-supplied value.
	 *
	 * @return string
	 */
	protected function escape( string $value ): string
	{
		return htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8' );
	}
}
