/**
 * Core Web Vitals real-user monitoring (RUM) client.
 *
 * Collects the five Core Web Vitals — LCP (Largest Contentful Paint),
 * FID (First Input Delay), CLS (Cumulative Layout Shift), INP
 * (Interaction to Next Paint), and TTFB (Time to First Byte) — and
 * posts each one to the package's metrics endpoint as soon as it
 * resolves.
 *
 * The script reads its configuration from the
 * `window.ArtisanPackPerformance.monitor` global set by the
 * `@perfMonitor` Blade directive, so a single bundled module can be
 * reused across pages without re-bundling. When the global is absent
 * (e.g. the directive wasn't included on the page) the module
 * silently no-ops — being defensive here keeps a misconfigured page
 * from emitting JavaScript errors that drown out the real signal.
 *
 * Transport uses `navigator.sendBeacon` when available so metrics
 * survive page unload (the canonical case for LCP/CLS, which
 * frequently finalize during `visibilitychange:hidden`). Falls back
 * to `fetch` with `keepalive: true` for browsers that don't expose
 * `sendBeacon` against the destination origin.
 *
 * Sampling is applied client-side so traffic shaping happens before
 * the network round-trip — `sample_rate=10` ships one in ten
 * sessions worth of data, not one in ten metrics from every session,
 * because mixing the two skews per-session percentiles.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

import { onCLS, onFID, onINP, onLCP, onTTFB } from 'web-vitals';

const DEFAULT_ENDPOINT = '/api/performance/metrics';
const DEFAULT_SAMPLE_RATE = 100;

/**
 * Reads the directive-provided configuration off `window`.
 *
 * Returns a defensively-cloned object so the runtime can't mutate
 * the source-of-truth global. Falls back to sensible defaults when
 * the directive wasn't included on the page.
 *
 * @returns {{endpoint: string, sampleRate: number, csrfToken: ?string, page: ?string, route: ?string, extra: object}}
 */
function readConfig() {
	const source = ( typeof window !== 'undefined' && window.ArtisanPackPerformance && window.ArtisanPackPerformance.monitor )
		? window.ArtisanPackPerformance.monitor
		: {};

	return {
		endpoint:   typeof source.endpoint === 'string' && source.endpoint.length > 0 ? source.endpoint : DEFAULT_ENDPOINT,
		sampleRate: typeof source.sampleRate === 'number' && Number.isFinite( source.sampleRate ) ? source.sampleRate : DEFAULT_SAMPLE_RATE,
		csrfToken:  typeof source.csrfToken === 'string' ? source.csrfToken : null,
		page:       typeof source.page === 'string' ? source.page : ( typeof window !== 'undefined' ? window.location.pathname : null ),
		route:      typeof source.route === 'string' ? source.route : null,
		extra:      ( source.extra && typeof source.extra === 'object' ) ? source.extra : {},
	};
}

/**
 * Decides whether the current session should report metrics.
 *
 * Uses `Math.random()` once per page load so a session either ships
 * ALL of its metrics or NONE of them. Mixing partial sets would skew
 * per-session percentile calculations.
 *
 * @param {number} sampleRate Percentage of sessions to include (0-100).
 * @returns {boolean}
 */
function shouldSample( sampleRate ) {
	if ( sampleRate <= 0 ) {
		return false;
	}

	if ( sampleRate >= 100 ) {
		return true;
	}

	return Math.random() * 100 < sampleRate;
}

/**
 * Captures static context once per session.
 *
 * Connection type comes from the Network Information API
 * (`navigator.connection.effectiveType`), which is only available in
 * Chromium-derived browsers. Device type is inferred from a tiny
 * UA-Client-Hints check; we don't UA-sniff for a finer breakdown
 * because the aggregation pipeline does the precise classification
 * server-side from the User-Agent header.
 *
 * @returns {{connection: ?string, deviceType: ?string}}
 */
function collectContext() {
	const connection = typeof navigator !== 'undefined' && navigator.connection
		? navigator.connection.effectiveType || null
		: null;

	const deviceType = typeof navigator !== 'undefined' && navigator.userAgentData && typeof navigator.userAgentData.mobile === 'boolean'
		? ( navigator.userAgentData.mobile ? 'mobile' : 'desktop' )
		: null;

	return { connection, deviceType };
}

/**
 * Posts a single metric payload to the configured endpoint.
 *
 * Prefers `sendBeacon` so metrics that resolve during page unload
 * still ship. Falls back to `fetch` with `keepalive: true` because
 * `sendBeacon` won't honor a custom `Content-Type` header — pages
 * that need `application/json` (the package default) end up on the
 * fetch path. CSRF tokens are sent both as a header and inside the
 * body so server endpoints can choose whichever verification mode
 * fits their middleware stack.
 *
 * @param {string} endpoint Destination URL.
 * @param {object} payload Metric envelope.
 * @param {?string} csrfToken Optional CSRF token to attach.
 * @returns {void}
 */
function send( endpoint, payload, csrfToken ) {
	const body = JSON.stringify( payload );

	if ( typeof navigator !== 'undefined' && typeof navigator.sendBeacon === 'function' ) {
		try {
			const blob = new Blob( [ body ], { type: 'application/json' } );

			if ( navigator.sendBeacon( endpoint, blob ) ) {
				return;
			}
		} catch ( error ) {
			// Some browsers reject Blob payloads with custom types
			// (Safari historically) — fall through to fetch.
		}
	}

	if ( typeof fetch !== 'function' ) {
		return;
	}

	const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };

	if ( csrfToken ) {
		headers[ 'X-CSRF-TOKEN' ] = csrfToken;
	}

	fetch( endpoint, {
		method:    'POST',
		headers,
		body,
		keepalive: true,
		// Performance metrics never need credentials by default —
		// keeping CORS preflight off the hot path saves a round trip
		// the user is actively waiting for.
		credentials: 'same-origin',
	} ).catch( () => {
		// Metric loss is acceptable; we never want a failed beacon to
		// surface as an unhandled rejection in the host page's
		// console.
	} );
}

/**
 * Builds the metric envelope sent to the API endpoint.
 *
 * @param {object} metric web-vitals metric instance.
 * @param {object} config Resolved configuration.
 * @param {{connection: ?string, deviceType: ?string}} context Static context.
 * @returns {object}
 */
function buildPayload( metric, config, context ) {
	return {
		name:        metric.name,
		value:       metric.value,
		delta:       metric.delta,
		id:          metric.id,
		rating:      metric.rating || null,
		navigationType: metric.navigationType || null,
		page:        config.page,
		route:       config.route,
		connection:  context.connection,
		deviceType:  context.deviceType,
		extra:       config.extra,
		timestamp:   Date.now(),
	};
}

/**
 * Wires the web-vitals observers to the reporting pipeline.
 *
 * Each metric handler is registered with `reportAllChanges: true` so
 * CLS/INP — which can update over the session's lifetime — emit
 * intermediate values rather than only the final reading. The
 * aggregation pipeline server-side dedupes by metric `id` so the
 * extra reports don't double-count.
 *
 * @returns {boolean} Whether instrumentation actually started.
 */
function start() {
	if ( typeof window === 'undefined' ) {
		return false;
	}

	const config = readConfig();

	if ( ! shouldSample( config.sampleRate ) ) {
		return false;
	}

	const context = collectContext();

	const report = ( metric ) => {
		send( config.endpoint, buildPayload( metric, config, context ), config.csrfToken );
	};

	onLCP( report, { reportAllChanges: false } );
	onFID( report );
	onCLS( report, { reportAllChanges: true } );
	onINP( report, { reportAllChanges: true } );
	onTTFB( report );

	return true;
}

const api = {
	start,
	readConfig,
	shouldSample,
	collectContext,
	buildPayload,
	send,
};

if ( typeof window !== 'undefined' ) {
	window.ArtisanPackPerformance = window.ArtisanPackPerformance || {};
	window.ArtisanPackPerformance.webVitals = api;

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', () => start() );
	} else {
		start();
	}
}

export default api;
export { start, readConfig, shouldSample, collectContext, buildPayload, send };
