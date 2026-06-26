<?php

/**
 * Responsive image Blade component.
 *
 * Renders a `<picture>` element with AVIF and WebP `<source>` entries (when
 * the active driver supports them) and an `<img>` fallback. Variants are
 * resolved against the `ResponsiveImageGenerator` so callers only supply the
 * source path and optional sizes — the component computes srcsets and the
 * effective widths.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\View\Components;

use ArtisanPackUI\Performance\Images\ResponsiveImageGenerator;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Throwable;

/**
 * Responsive image component class.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @since      1.0.0
 */
final class ResponsiveImage extends Component
{
	/**
	 * Resolved srcset value for the AVIF `<source>` element.
	 *
	 * Empty string when AVIF cannot be produced.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $avifSrcset = '';

	/**
	 * Resolved srcset value for the WebP `<source>` element.
	 *
	 * Empty string when WebP cannot be produced.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $webpSrcset = '';

	/**
	 * Resolved srcset value for the original-format `<img>` fallback.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $fallbackSrcset = '';

	/**
	 * Resolved fallback `<img>` src.
	 *
	 * Defaults to the original source when generation produces no widths.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $fallbackSrc;

	/**
	 * Absolute filesystem path used for variant generation.
	 *
	 * Null when the source is a remote URL or cannot be mapped to a file.
	 *
	 * @since 1.0.0
	 *
	 * @var string|null
	 */
	protected ?string $sourceFile = null;

	/**
	 * Public URL prefix applied to generated variant filenames.
	 *
	 * Generation produces filesystem paths; the component swaps the file
	 * directory for this URL prefix so the srcset entries are loadable by
	 * the browser. Null when the component cannot derive a safe URL prefix
	 * (e.g. caller passed an absolute filesystem path outside `public_path()`)
	 * — the component degrades to a bare `<img>` in that case.
	 *
	 * @since 1.0.0
	 *
	 * @var string|null
	 */
	protected ?string $srcsetBaseUrl = null;

	/**
	 * Creates a new responsive image component instance.
	 *
	 * @since 1.0.0
	 *
	 * @param string                  $src             Absolute path or URL to the source image.
	 * @param string                  $alt             Alt text for accessibility.
	 * @param array<int, int>|null    $sizes           Widths to generate (defaults to package config).
	 * @param string|null             $sizesAttr       Value for the `sizes` attribute (e.g. `(max-width: 640px) 100vw, 50vw`).
	 * @param int|null                $width           Width in pixels (recommended for CLS).
	 * @param int|null                $height          Height in pixels (recommended for CLS).
	 * @param bool                    $lazy            Whether to render `loading="lazy"`.
	 * @param string|null             $placeholder     Placeholder strategy forwarded to the underlying `<img>`.
	 * @param string|null             $dominantColor   Hex color used when `placeholder=dominant_color`.
	 * @param string|null             $fetchpriority   Fetchpriority hint forwarded to the underlying `<img>`.
	 * @param array<int, string>|null $formats         Formats to emit (defaults to enabled formats).
	 * @param string|null             $class           CSS classes added to the `<picture>` element.
	 * @param string|null             $imgClass        CSS classes added to the `<img>` element.
	 */
	public function __construct(
		public string $src,
		public string $alt = '',
		public ?array $sizes = null,
		public ?string $sizesAttr = null,
		public ?int $width = null,
		public ?int $height = null,
		public bool $lazy = true,
		public ?string $placeholder = null,
		public ?string $dominantColor = null,
		public ?string $fetchpriority = null,
		public ?array $formats = null,
		public ?string $class = null,
		public ?string $imgClass = null,
	) {
		$this->fallbackSrc = $src;
		$this->resolveSources();
	}

	/**
	 * Returns the view to render.
	 *
	 * @since 1.0.0
	 *
	 * @return View
	 */
	public function render(): View
	{
		return view( 'performance::components.responsive-image' );
	}

	/**
	 * Resolves srcset values for AVIF, WebP, and fallback variants.
	 *
	 * Generation runs against the filesystem path resolved from `src`. The
	 * resulting variant filenames are mapped back to web-loadable URLs by
	 * replacing the file directory with the URL directory of `src`. When the
	 * source can't be mapped to a local file (remote URL, unresolved relative
	 * path) the component falls back to a bare `<img>` so callers still get a
	 * usable element.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function resolveSources(): void
	{
		$this->sourceFile = $this->resolveSourceFile();

		if ( null === $this->sourceFile ) {
			return;
		}

		$this->srcsetBaseUrl = $this->resolveSrcsetBaseUrl();

		// No URL prefix means the variants can't be expressed as web URLs —
		// emit a bare <img> rather than leaking filesystem paths into srcset.
		if ( null === $this->srcsetBaseUrl ) {
			return;
		}

		$generator = $this->resolveGenerator();
		$formats   = $this->resolveFormats();

		try {
			$this->fallbackSrcset = $this->rewriteSrcset(
				$generator->generateSrcset( $this->sourceFile, $this->sizes ),
			);

			if ( in_array( 'avif', $formats, true ) && $generator->images()->supportsFormat( 'avif' ) ) {
				$this->avifSrcset = $this->rewriteSrcset(
					$generator->generateSrcset( $this->sourceFile, $this->sizes, 'avif' ),
				);
			}

			if ( in_array( 'webp', $formats, true ) && $generator->images()->supportsFormat( 'webp' ) ) {
				$this->webpSrcset = $this->rewriteSrcset(
					$generator->generateSrcset( $this->sourceFile, $this->sizes, 'webp' ),
				);
			}
		} catch ( Throwable ) {
			// Generation failed (unreadable file, unsupported driver) — fall
			// back to the bare `<img src>` so the component never 500s.
			$this->avifSrcset     = '';
			$this->webpSrcset     = '';
			$this->fallbackSrcset = '';
		}
	}

	/**
	 * Resolves `$this->src` to an absolute filesystem path.
	 *
	 * Accepts absolute paths verbatim and maps web-relative paths through
	 * `public_path()` so callers can pass `/storage/foo.jpg` and have the
	 * component resolve to the real file behind the public symlink.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null Absolute path to the file, or null when it cannot be located.
	 */
	protected function resolveSourceFile(): ?string
	{
		if ( str_contains( $this->src, '://' ) ) {
			return null;
		}

		if ( is_file( $this->src ) ) {
			return $this->src;
		}

		if ( ! function_exists( 'public_path' ) ) {
			return null;
		}

		$candidate = public_path( ltrim( $this->src, '/' ) );

		return is_file( $candidate ) ? $candidate : null;
	}

	/**
	 * Resolves the URL directory the srcset entries should be prefixed with.
	 *
	 * Web URLs use the directory of `$this->src` verbatim. Web-relative paths
	 * (those starting with `/`) only qualify when the resolved filesystem
	 * path also lives under `public_path()` — otherwise the path is a
	 * filesystem path (e.g. `/tmp/foo.jpg`) and using its directory as a URL
	 * prefix would leak filesystem paths into the rendered srcset. The
	 * web-root case (`dirname('/foo.jpg') === '/'`) is preserved as `/` so
	 * srcset entries become `/foo-320w.jpg` rather than the empty string.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null URL prefix (no trailing slash, `/` for web-root), or null when none can be derived.
	 */
	protected function resolveSrcsetBaseUrl(): ?string
	{
		if ( null === $this->sourceFile ) {
			return null;
		}

		$isUrl           = str_contains( $this->src, '://' );
		$startsWithSlash = str_starts_with( $this->src, '/' );

		if ( $isUrl || ( $startsWithSlash && $this->sourceFileIsUnderPublicPath() ) ) {
			$dir = dirname( $this->src );

			// dirname('/foo.jpg') === '/' — preserve the root slash so
			// rewriteSrcset emits `/foo-320w.jpg` and not `foo-320w.jpg`.
			if ( '/' === $dir ) {
				return '/';
			}

			return rtrim( $dir, '/' );
		}

		// Web-relative-looking `src` that doesn't actually live under
		// public_path → refuse to emit srcsets, since we'd leak a filesystem
		// path. The caller still gets a bare <img src="$src"> via $fallbackSrc.
		if ( $startsWithSlash ) {
			return null;
		}

		// Otherwise derive a URL from the file path by stripping public_path.
		if ( ! function_exists( 'public_path' ) ) {
			return null;
		}

		$publicRoot = rtrim( public_path(), DIRECTORY_SEPARATOR );

		if ( str_starts_with( $this->sourceFile, $publicRoot . DIRECTORY_SEPARATOR ) ) {
			$relative = substr( $this->sourceFile, strlen( $publicRoot ) );
			$dir      = str_replace( DIRECTORY_SEPARATOR, '/', dirname( $relative ) );

			return '/' === $dir ? '/' : '/' . trim( $dir, '/' );
		}

		return null;
	}

	/**
	 * Reports whether the resolved source file lives under `public_path()`.
	 *
	 * Used to decide whether a `/`-prefixed `src` should be treated as a web
	 * path (safe to use its directory as a srcset URL prefix) or as a raw
	 * filesystem path (must not be exposed to the browser).
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	protected function sourceFileIsUnderPublicPath(): bool
	{
		if ( null === $this->sourceFile || ! function_exists( 'public_path' ) ) {
			return false;
		}

		$publicRoot = rtrim( public_path(), DIRECTORY_SEPARATOR );

		return str_starts_with( $this->sourceFile, $publicRoot . DIRECTORY_SEPARATOR );
	}

	/**
	 * Rewrites a srcset string so each path entry is prefixed with the URL base.
	 *
	 * The generator emits `<filesystem-path> <width>w` tuples; the component
	 * swaps the filesystem directory for `$srcsetBaseUrl` so the browser can
	 * actually fetch the variants.
	 *
	 * @since 1.0.0
	 *
	 * @param string $srcset Raw srcset string from the generator.
	 *
	 * @return string Srcset string with web-loadable URLs.
	 */
	protected function rewriteSrcset( string $srcset ): string
	{
		if ( '' === $srcset || null === $this->srcsetBaseUrl ) {
			return $srcset;
		}

		// Strip any trailing slash so the join never produces `//`. For the
		// web-root case (`srcsetBaseUrl === '/'`) this collapses to '' and
		// the join produces `/filename` — exactly what we want.
		$prefix  = rtrim( $this->srcsetBaseUrl, '/' );
		$entries = array_map( 'trim', explode( ',', $srcset ) );
		$mapped  = [];

		foreach ( $entries as $entry ) {
			if ( '' === $entry ) {
				continue;
			}

			// Split on the LAST space — the descriptor (`<n>w` / `<n>x`) is
			// always the trailing token, and filesystem paths may legitimately
			// contain spaces (a real concern: paths like
			// `/Users/.../ArtisanPack UI Packages/.../foo.jpg`). Splitting on
			// the first space would slice the path in half.
			$lastSpace = strrpos( $entry, ' ' );

			if ( false === $lastSpace ) {
				$path       = $entry;
				$descriptor = '';
			} else {
				$path       = substr( $entry, 0, $lastSpace );
				$descriptor = substr( $entry, $lastSpace + 1 );
			}

			$mapped[] = $prefix . '/' . basename( $path ) . ( '' !== $descriptor ? ' ' . $descriptor : '' );
		}

		return implode( ', ', $mapped );
	}

	/**
	 * Resolves the formats to emit from the caller or config.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	protected function resolveFormats(): array
	{
		if ( null !== $this->formats ) {
			return array_values( array_map( 'strtolower', $this->formats ) );
		}

		$configured = (array) config( 'artisanpack.performance.images.formats', [] );
		$enabled    = [];

		foreach ( $configured as $format => $settings ) {
			if ( ! empty( $settings['enabled'] ) ) {
				$enabled[] = strtolower( (string) $format );
			}
		}

		return $enabled;
	}

	/**
	 * Resolves the responsive generator from the container with a sensible fallback.
	 *
	 * @since 1.0.0
	 *
	 * @return ResponsiveImageGenerator
	 */
	protected function resolveGenerator(): ResponsiveImageGenerator
	{
		if ( function_exists( 'app' ) ) {
			try {
				return app( ResponsiveImageGenerator::class );
			} catch ( Throwable ) {
				// Container missing the binding (test edge cases) — fall through.
			}
		}

		return new ResponsiveImageGenerator();
	}
}
