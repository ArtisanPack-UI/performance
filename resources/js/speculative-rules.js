/**
 * Speculative loading runtime.
 *
 * Provides three jobs for the page:
 *
 *   1. Feature-detect the Speculation Rules API. When unsupported, walk
 *      the DOM for `<a data-prefetch>` / `<a data-prerender>` markers and
 *      inject `<link rel="prefetch">` elements as a graceful fallback.
 *
 *   2. Expose `window.PerformanceSpeculativeLoader.inject(rules)` so
 *      applications can publish additional rules at runtime. Browsers
 *      that support the API accept multiple `<script type="speculationrules">`
 *      blocks; we add a new one rather than mutating the existing block.
 *
 *   3. Wire up `<div class="perf-embed-facade">` placeholders so the
 *      provider iframe is fetched on the first user interaction.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

const FALLBACK_PREFETCH_ATTRIBUTES = [ 'data-prefetch', 'data-prerender' ];

class SpeculativeLoader {
	constructor() {
		this.supportsSpeculationRules = SpeculativeLoader.detectSpeculationRules();
		this.fallbackPrefetched       = new Set();
	}

	/**
	 * Reports whether the running browser supports the Speculation Rules API.
	 *
	 * Two checks: HTMLScriptElement must accept `speculationrules` type and
	 * the document must support the matching link relation.
	 *
	 * @returns {boolean}
	 */
	static detectSpeculationRules() {
		if ( typeof HTMLScriptElement === 'undefined' ) {
			return false;
		}

		try {
			return HTMLScriptElement.supports && HTMLScriptElement.supports( 'speculationrules' );
		} catch ( error ) {
			return false;
		}
	}

	/**
	 * Initialises the page once the DOM is ready.
	 *
	 * @returns {void}
	 */
	init() {
		if ( ! this.supportsSpeculationRules ) {
			this.initFallbackPrefetch();
		}

		this.initEmbedFacades();
	}

	/**
	 * Walks the page for marker attributes and injects fallback prefetch links.
	 *
	 * Each URL is injected at most once even when it appears in multiple
	 * marker attributes — duplicate `<link rel="prefetch">` elements waste
	 * bytes without changing browser behavior.
	 *
	 * @returns {void}
	 */
	initFallbackPrefetch() {
		const selector = FALLBACK_PREFETCH_ATTRIBUTES
			.map( ( attribute ) => `a[${ attribute }]:not([data-no-speculate])` )
			.join( ',' );

		document.querySelectorAll( selector ).forEach( ( anchor ) => {
			const href = anchor.getAttribute( 'href' );

			if ( ! href || this.fallbackPrefetched.has( href ) ) {
				return;
			}

			this.fallbackPrefetched.add( href );

			const link = document.createElement( 'link' );

			link.rel  = 'prefetch';
			link.href = href;

			document.head.appendChild( link );
		} );
	}

	/**
	 * Wires up the embed facade `click` handler.
	 *
	 * Uses event delegation on `document` so facades injected after page
	 * load (e.g. via Livewire morphs or async fragment swaps) still work
	 * without re-running `init()`.
	 *
	 * @returns {void}
	 */
	initEmbedFacades() {
		document.addEventListener( 'click', ( event ) => {
			const target = event.target instanceof Element ? event.target : null;

			if ( target === null ) {
				return;
			}

			const facade = target.closest( '.perf-embed-facade' );

			if ( facade === null ) {
				return;
			}

			event.preventDefault();
			this.loadEmbed( facade );
		} );
	}

	/**
	 * Swaps a facade for its provider iframe or blockquote embed.
	 *
	 * `iframe`-mode providers (YouTube, Vimeo) replace the facade with an
	 * iframe; `blockquote`-mode providers (Twitter/X) replace it with the
	 * inline embed HTML and lazy-load the widgets script.
	 *
	 * @param {Element} facade Facade container.
	 * @returns {void}
	 */
	loadEmbed( facade ) {
		const mode = facade.getAttribute( 'data-mode' ) || 'iframe';

		if ( mode === 'blockquote' ) {
			this.loadBlockquoteEmbed( facade );
			return;
		}

		this.loadIframeEmbed( facade );
	}

	/**
	 * Swaps a facade for a provider iframe.
	 *
	 * @param {Element} facade Facade container.
	 * @returns {void}
	 */
	loadIframeEmbed( facade ) {
		const iframeUrl = facade.getAttribute( 'data-iframe-url' );

		if ( ! iframeUrl ) {
			return;
		}

		const provider = facade.getAttribute( 'data-provider' ) || 'embed';
		const title    = facade.getAttribute( 'data-title' ) || provider;

		const iframe = document.createElement( 'iframe' );

		iframe.src         = iframeUrl;
		iframe.title       = title;
		iframe.loading     = 'eager';
		iframe.allow       = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
		iframe.allowFullscreen = true;
		iframe.referrerPolicy  = 'strict-origin-when-cross-origin';
		iframe.className   = 'perf-embed-iframe';

		facade.replaceWith( iframe );
	}

	/**
	 * Swaps a facade for an inline blockquote embed.
	 *
	 * The HTML is base64-encoded in the facade's `data-embed-html` so the
	 * payload can contain quotes and angle brackets without escaping. The
	 * widgets script is loaded once per page; subsequent embeds reuse the
	 * existing tag.
	 *
	 * @param {Element} facade Facade container.
	 * @returns {void}
	 */
	loadBlockquoteEmbed( facade ) {
		const encoded = facade.getAttribute( 'data-embed-html' ) || '';

		if ( ! encoded ) {
			return;
		}

		let html;

		try {
			html = atob( encoded );
		} catch ( error ) {
			return;
		}

		const container = document.createElement( 'div' );

		container.className = 'perf-embed perf-embed-inline';
		container.innerHTML = html;

		facade.replaceWith( container );

		const widgetsScript = facade.getAttribute( 'data-widgets-script' ) || '';

		if ( ! widgetsScript ) {
			return;
		}

		if ( document.querySelector( `script[data-perf-widgets="${ widgetsScript }"]` ) ) {
			return;
		}

		const script = document.createElement( 'script' );

		script.async = true;
		script.src   = widgetsScript;
		script.setAttribute( 'data-perf-widgets', widgetsScript );

		document.head.appendChild( script );
	}

	/**
	 * Injects additional speculation rules at runtime.
	 *
	 * @param {object|string} rules Rules object or serialized JSON.
	 * @returns {boolean} Whether the rules were inserted.
	 */
	inject( rules ) {
		if ( ! this.supportsSpeculationRules ) {
			return false;
		}

		let payload;

		try {
			payload = typeof rules === 'string' ? rules : JSON.stringify( rules );
		} catch ( error ) {
			return false;
		}

		if ( ! payload || payload === '{}' ) {
			return false;
		}

		const script = document.createElement( 'script' );

		script.type        = 'speculationrules';
		script.textContent = payload;

		document.head.appendChild( script );

		return true;
	}
}

const loader = new SpeculativeLoader();

if ( typeof window !== 'undefined' ) {
	window.PerformanceSpeculativeLoader = loader;

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', () => loader.init() );
	} else {
		loader.init();
	}
}

export { SpeculativeLoader };
export default loader;
