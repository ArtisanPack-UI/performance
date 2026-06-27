<?php

/**
 * Resource hint value object.
 *
 * Lightweight DTO describing a single browser resource hint
 * (`preconnect`, `dns-prefetch`, `preload`, `prefetch`) and the optional
 * loader metadata associated with it (`as`, `type`, `crossorigin`,
 * `media`, `fetchpriority`, `referrerpolicy`). Renders itself as either
 * an HTML `<link>` element or as a `Link` response-header value so the
 * same record can drive both the injected `<head>` markup and HTTP/2
 * server-push headers without re-parsing.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Output;

use InvalidArgumentException;

/**
 * Resource hint value object.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @since      1.0.0
 */
final class ResourceHint
{
	/**
	 * Whitelisted `rel` values for browser resource hints.
	 *
	 * The HTML spec recognizes additional `rel` tokens, but this package
	 * scopes itself to the four hint types that actually influence the
	 * page load timeline. Other tokens would silently no-op in most
	 * browsers and confuse the dedup key by introducing rel-collisions
	 * with non-hint links (`canonical`, `stylesheet`, …).
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public const SUPPORTED_RELS = [ 'preconnect', 'dns-prefetch', 'preload', 'prefetch' ];

	/**
	 * Whitelisted `as` values for preload hints.
	 *
	 * Mirrors the spec list. Used to drop unknown values rather than
	 * pass them through, since browsers refuse to honor preloads with
	 * an unknown `as` and an invalid attribute is worse than no
	 * attribute at all.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public const SUPPORTED_AS = [
		'audio',
		'document',
		'embed',
		'fetch',
		'font',
		'image',
		'object',
		'script',
		'style',
		'track',
		'video',
		'worker',
	];

	/**
	 * Whitelisted `fetchpriority` values.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public const SUPPORTED_FETCHPRIORITY = [ 'high', 'low', 'auto' ];

	/**
	 * Whitelisted `crossorigin` values.
	 *
	 * The empty string is the bare `crossorigin` form (equivalent to
	 * `anonymous` per the HTML spec) and is preserved so callers can
	 * emit `<link crossorigin>` without a value.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public const SUPPORTED_CROSSORIGIN = [ '', 'anonymous', 'use-credentials' ];

	/**
	 * Resolved canonical `rel` value.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $rel;

	/**
	 * Resolved target URL/origin.
	 *
	 * Normalized via `filter_var` (no query-string trimming) so the
	 * dedup key is stable across whitespace and casing variations.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $href;

	/**
	 * Resolved `as` value (preload only).
	 *
	 * @since 1.0.0
	 *
	 * @var string|null
	 */
	public ?string $as;

	/**
	 * Resolved MIME type, if any.
	 *
	 * @since 1.0.0
	 *
	 * @var string|null
	 */
	public ?string $type;

	/**
	 * Resolved `crossorigin` value.
	 *
	 * Null means the attribute is absent; an empty string emits the
	 * bare `crossorigin` form per the HTML spec.
	 *
	 * @since 1.0.0
	 *
	 * @var string|null
	 */
	public ?string $crossorigin;

	/**
	 * Resolved media query, if any.
	 *
	 * @since 1.0.0
	 *
	 * @var string|null
	 */
	public ?string $media;

	/**
	 * Resolved fetchpriority hint, if any.
	 *
	 * @since 1.0.0
	 *
	 * @var string|null
	 */
	public ?string $fetchpriority;

	/**
	 * Resolved `referrerpolicy` value, if any.
	 *
	 * @since 1.0.0
	 *
	 * @var string|null
	 */
	public ?string $referrerpolicy;

	/**
	 * Creates a normalized resource hint.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $rel            Resource hint relation. Case-insensitive; mapped to lowercase.
	 * @param string      $href           Target URL/origin.
	 * @param string|null $as             Required for preload; honored on prefetch.
	 * @param string|null $type           MIME type (e.g. `font/woff2`).
	 * @param string|null $crossorigin    `anonymous`, `use-credentials`, or empty string for bare attribute.
	 * @param string|null $media          Media query controlling when the hint applies.
	 * @param string|null $fetchpriority  `high`, `low`, or `auto`.
	 * @param string|null $referrerpolicy Referrer policy override.
	 *
	 * @throws InvalidArgumentException When `rel`/`href` are missing or unsupported.
	 */
	public function __construct(
		string $rel,
		string $href,
		?string $as = null,
		?string $type = null,
		?string $crossorigin = null,
		?string $media = null,
		?string $fetchpriority = null,
		?string $referrerpolicy = null,
	) {
		$normalizedRel = strtolower( trim( $rel ) );

		if ( ! in_array( $normalizedRel, self::SUPPORTED_RELS, true ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Unsupported resource hint rel "%s".', $rel ),
			);
		}

		$normalizedHref = trim( $href );

		if ( '' === $normalizedHref ) {
			throw new InvalidArgumentException( 'Resource hint href cannot be empty.' );
		}

		$this->rel            = $normalizedRel;
		$this->href           = $normalizedHref;
		$this->as             = $this->normalizeAs( $as );
		$this->type           = $this->normalizeNonEmpty( $type );
		$this->crossorigin    = $this->normalizeCrossorigin( $crossorigin );
		$this->media          = $this->normalizeNonEmpty( $media );
		$this->fetchpriority  = $this->normalizeFetchpriority( $fetchpriority );
		$this->referrerpolicy = $this->normalizeNonEmpty( $referrerpolicy );
	}

	/**
	 * Builds a hint from a config-style associative array.
	 *
	 * Accepts both the verbose form (`['rel' => 'preconnect', 'href' => '...']`)
	 * and the shorthand form (`'https://fonts.googleapis.com'`) so the
	 * config arrays can list bare origin strings for preconnect/dns-prefetch
	 * and full descriptors for preload/prefetch without a different code
	 * path per rel.
	 *
	 * @since 1.0.0
	 *
	 * @param  string                                              $rel  Default `rel` to apply when the entry omits it.
	 * @param  array{rel?: string, href?: string, as?: string, type?: string, crossorigin?: string, media?: string, fetchpriority?: string, referrerpolicy?: string}|string $entry Config entry.
	 *
	 * @return self|null Null when the entry can't be coerced into a valid hint.
	 */
	public static function fromConfigEntry( string $rel, string|array $entry ): ?self
	{
		if ( is_string( $entry ) ) {
			$entry = [ 'href' => $entry ];
		}

		$resolvedRel  = (string) ( $entry['rel'] ?? $rel );
		$resolvedHref = (string) ( $entry['href'] ?? '' );

		if ( '' === trim( $resolvedHref ) ) {
			return null;
		}

		try {
			return new self(
				$resolvedRel,
				$resolvedHref,
				isset( $entry['as'] ) ? (string) $entry['as'] : null,
				isset( $entry['type'] ) ? (string) $entry['type'] : null,
				isset( $entry['crossorigin'] ) ? (string) $entry['crossorigin'] : null,
				isset( $entry['media'] ) ? (string) $entry['media'] : null,
				isset( $entry['fetchpriority'] ) ? (string) $entry['fetchpriority'] : null,
				isset( $entry['referrerpolicy'] ) ? (string) $entry['referrerpolicy'] : null,
			);
		} catch ( InvalidArgumentException ) {
			return null;
		}
	}

	/**
	 * Reports whether the hint's values are safe to emit as a `Link` header.
	 *
	 * Rejects any field whose value contains characters that would break
	 * the RFC 8288 grammar — CR/LF (header smuggling), `"` (the
	 * quoted-string delimiter for `type`/`media`/`referrerpolicy`), or
	 * `<`/`>` in the URL-reference portion. The HTML rendering path is
	 * not affected because `toLinkElement()` HTML-escapes every value
	 * before composition.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function isSafeForLinkHeader(): bool
	{
		$fields = [
			$this->href,
			$this->type,
			$this->media,
			$this->referrerpolicy,
		];

		foreach ( $fields as $value ) {
			if ( null === $value ) {
				continue;
			}

			if ( 1 === preg_match( '/[\r\n"<>]/', $value ) ) {
				return false;
			}
		}

		// href additionally must not contain whitespace inside its URL.
		return 1 !== preg_match( '/\s/', $this->href );
	}

	/**
	 * Returns a stable dedup key for `(rel, href, as)`.
	 *
	 * Including `as` in the key preserves the spec-allowed case where the
	 * same origin is preloaded as multiple types (e.g. `font` and `style`).
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function dedupKey(): string
	{
		return $this->rel . '|' . strtolower( $this->href ) . '|' . ( $this->as ?? '' );
	}

	/**
	 * Renders the hint as an HTML `<link>` element.
	 *
	 * Output is HTML-escaped so caller-supplied values can be emitted
	 * directly into a Blade layout without an additional escape pass.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function toLinkElement(): string
	{
		$parts = [
			'rel="' . $this->escape( $this->rel ) . '"',
			'href="' . $this->escape( $this->href ) . '"',
		];

		if ( null !== $this->as ) {
			$parts[] = 'as="' . $this->escape( $this->as ) . '"';
		}

		if ( null !== $this->type ) {
			$parts[] = 'type="' . $this->escape( $this->type ) . '"';
		}

		if ( null !== $this->crossorigin ) {
			$parts[] = '' === $this->crossorigin
				? 'crossorigin'
				: 'crossorigin="' . $this->escape( $this->crossorigin ) . '"';
		}

		if ( null !== $this->media ) {
			$parts[] = 'media="' . $this->escape( $this->media ) . '"';
		}

		if ( null !== $this->fetchpriority ) {
			$parts[] = 'fetchpriority="' . $this->escape( $this->fetchpriority ) . '"';
		}

		if ( null !== $this->referrerpolicy ) {
			$parts[] = 'referrerpolicy="' . $this->escape( $this->referrerpolicy ) . '"';
		}

		return '<link ' . implode( ' ', $parts ) . '>';
	}

	/**
	 * Renders the hint as a single RFC 8288 `Link` header value.
	 *
	 * The returned string is the value portion only (e.g.
	 * `<https://fonts.googleapis.com>; rel=preconnect`). Callers join
	 * multiple values with `, ` per the header-list grammar.
	 *
	 * Header values are NOT quote-escaped here — the surrounding
	 * injector is responsible for filtering out hrefs containing CR/LF
	 * or `<`/`>` that would break the header structure.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function toLinkHeader(): string
	{
		$parts = [ '<' . $this->href . '>', 'rel=' . $this->rel ];

		if ( null !== $this->as ) {
			$parts[] = 'as=' . $this->as;
		}

		if ( null !== $this->type ) {
			$parts[] = 'type="' . $this->type . '"';
		}

		if ( null !== $this->crossorigin ) {
			$parts[] = '' === $this->crossorigin ? 'crossorigin' : 'crossorigin=' . $this->crossorigin;
		}

		if ( null !== $this->media ) {
			$parts[] = 'media="' . $this->media . '"';
		}

		if ( null !== $this->fetchpriority ) {
			$parts[] = 'fetchpriority=' . $this->fetchpriority;
		}

		if ( null !== $this->referrerpolicy ) {
			$parts[] = 'referrerpolicy=' . $this->referrerpolicy;
		}

		return implode( '; ', $parts );
	}

	/**
	 * Normalizes the `as` attribute, dropping unsupported values.
	 *
	 * @since 1.0.0
	 *
	 * @param  string|null $value Raw value.
	 *
	 * @return string|null
	 */
	protected function normalizeAs( ?string $value ): ?string
	{
		if ( null === $value ) {
			return null;
		}

		$lower = strtolower( trim( $value ) );

		if ( '' === $lower ) {
			return null;
		}

		return in_array( $lower, self::SUPPORTED_AS, true ) ? $lower : null;
	}

	/**
	 * Normalizes the `crossorigin` attribute, dropping unsupported values.
	 *
	 * @since 1.0.0
	 *
	 * @param  string|null $value Raw value.
	 *
	 * @return string|null
	 */
	protected function normalizeCrossorigin( ?string $value ): ?string
	{
		if ( null === $value ) {
			return null;
		}

		$lower = strtolower( trim( $value ) );

		return in_array( $lower, self::SUPPORTED_CROSSORIGIN, true ) ? $lower : null;
	}

	/**
	 * Normalizes the `fetchpriority` attribute, dropping unsupported values.
	 *
	 * @since 1.0.0
	 *
	 * @param  string|null $value Raw value.
	 *
	 * @return string|null
	 */
	protected function normalizeFetchpriority( ?string $value ): ?string
	{
		if ( null === $value ) {
			return null;
		}

		$lower = strtolower( trim( $value ) );

		if ( '' === $lower ) {
			return null;
		}

		return in_array( $lower, self::SUPPORTED_FETCHPRIORITY, true ) ? $lower : null;
	}

	/**
	 * Returns the trimmed value or null when empty.
	 *
	 * @since 1.0.0
	 *
	 * @param  string|null $value Raw value.
	 *
	 * @return string|null
	 */
	protected function normalizeNonEmpty( ?string $value ): ?string
	{
		if ( null === $value ) {
			return null;
		}

		$trimmed = trim( $value );

		return '' === $trimmed ? null : $trimmed;
	}

	/**
	 * HTML-escapes a value for safe insertion into attribute syntax.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $value Raw value.
	 *
	 * @return string
	 */
	protected function escape( string $value ): string
	{
		return htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8' );
	}
}
