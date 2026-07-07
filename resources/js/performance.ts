/**
 * ArtisanPack UI — Performance client API.
 *
 * Vanilla browser client that wraps the package's JSON admin endpoints
 * (dashboard/metrics/cache/queries/recommendations). Exposed here so the
 * React and Vue companion components can share a single fetch layer, and
 * apps that don't use either framework can still drive the endpoints
 * from plain JS.
 *
 * @since 1.0.0
 */

export type WebVitalName = 'LCP' | 'FID' | 'INP' | 'CLS' | 'TTFB' | 'FCP';

export type WebVitalStatus = 'good' | 'needs-improvement' | 'poor' | 'unknown';

export type DateRangeKey = '24h' | '7d' | '30d' | '90d';

export type ChartRangeKey = '7d' | '30d' | '90d';

export type QuerySortKey = 'time' | 'frequency';

export interface OverviewRow {
	metric: string;
	p75: number | null;
	sample_count: number;
	status: WebVitalStatus;
}

export interface PageRow {
	route: string | null;
	metric: string;
	p75: number;
	sample_count: number;
}

export interface CacheSummary {
	page: { entries: number; size_bytes?: number | null; hit_rate?: number | null };
	fragment: { entries: number; tags: number; hit_rate?: number | null };
}

export interface DashboardPayload {
	range: DateRangeKey;
	overview: OverviewRow[];
	pages: PageRow[];
	cache: CacheSummary;
}

export interface ChartDataset {
	metric: string;
	values: Array<number | null>;
	threshold: number | null;
}

export interface ChartPayload {
	type: 'line' | 'bar' | 'area';
	labels: string[];
	datasets: ChartDataset[];
	colors: {
		good: string;
		needs_improvement: string;
		poor: string;
	};
}

export interface PageEntry {
	key: string;
	path: string;
	size_bytes?: number | null;
	last_used_at?: string | null;
}

export interface FragmentTag {
	tag: string;
	entry_count: number;
}

export interface CachePayload {
	summary: CacheSummary;
	page_entries: PageEntry[];
	fragment_tags: FragmentTag[];
}

export interface CacheActionResult {
	action: string;
	message: string;
	is_error: boolean;
	summary?: CacheSummary;
	page_entries?: PageEntry[];
	fragment_tags?: FragmentTag[];
}

export interface SlowQueryRow {
	hash: string;
	query: string;
	normalized: string;
	peak_time_ms: number;
	avg_time_ms: number;
	occurrences: number;
	route: string | null;
	file: string | null;
	line: number | null;
	last_seen: string | null;
	suggestion: string | null;
}

export interface QueriesPayload {
	rows: SlowQueryRow[];
	available_routes: string[];
	sort: QuerySortKey;
}

export interface Recommendation {
	id: string;
	priority: 'high' | 'medium' | 'low';
	title: string;
	description?: string;
	manual_steps?: string[];
	action?: string;
	action_payload?: Record<string, unknown>;
}

export interface RecommendationsPayload {
	items: Recommendation[];
	dismissed: string[];
}

export interface RecommendationActionResult {
	action: string;
	message: string;
	is_error: boolean;
	items?: Recommendation[];
	dismissed?: string[];
}

export interface QueriesQuery {
	range?: DateRangeKey;
	route?: string;
	min_time_ms?: number;
	sort?: QuerySortKey;
}

export type AiFeatureKey =
	| 'performance.query_insight'
	| 'performance.optimization_suggestion';

export type OptimizationLevel = 'high' | 'medium' | 'low';

export interface SuggestedIndex {
	table: string;
	columns: string[];
	rationale: string;
}

export interface QueryRewrite {
	original: string;
	suggested: string;
	rationale: string;
}

export interface QueryInsightInput {
	query: string;
	explain?: string | Record<string, unknown> | unknown[] | null;
	// `schema` is what the caller pasted into the schema hint textarea —
	// the agent accepts a decoded JSON structure, a plain-text description,
	// or nothing. The React/Vue/Livewire panels all parse it the same way
	// so the "JSON or text" placeholder is honest across surfaces.
	schema?: string | Record<string, unknown> | unknown[] | null;
	time_ms?: number | null;
	connection?: string | null;
}

export interface QueryInsight {
	summary: string;
	bottlenecks: string[];
	suggested_indexes: SuggestedIndex[];
	rewrites: QueryRewrite[];
	caveats: string[];
}

export interface OptimizationFocusArea {
	title: string;
	routes: string[];
	impact: OptimizationLevel;
	effort: OptimizationLevel;
	rationale: string;
	actions: string[];
}

export interface OptimizationMetricRow {
	metric: string;
	route?: string | null;
	p50?: number;
	p75?: number;
	p90?: number;
	p99?: number;
	samples?: number;
	device?: string | null;
	[key: string]: unknown;
}

export interface OptimizationSuggestionInput {
	range: { from: string; to: string };
	metrics: OptimizationMetricRow[];
	context?: {
		traffic_mix?: string;
		recent_changes?: string;
		business_priority?: string;
	};
}

export interface OptimizationSuggestion {
	summary: string;
	focus_areas: OptimizationFocusArea[];
	quick_wins: string[];
	caveats: string[];
}

export interface AiAgentResponse<TOutput> {
	data: TOutput;
	feature_key: AiFeatureKey;
}

export class PerformanceAiError extends Error {
	public readonly status: number;

	public readonly featureKey: AiFeatureKey | null;

	constructor( message: string, status: number, featureKey: AiFeatureKey | null = null ) {
		super( message );
		this.name = 'PerformanceAiError';
		this.status = status;
		this.featureKey = featureKey;
	}
}

export interface WebVitalReport {
	name: WebVitalName;
	value: number;
	delta?: number;
	id?: string;
	rating?: string;
	page?: string;
	route?: string;
	device?: string;
	deviceType?: string;
	connection?: string;
	extra?: Record<string, unknown>;
}

export interface PerformanceClientOptions {
	/** Base URL for the JSON API. Trailing slashes are trimmed. */
	baseUrl?: string;
	/** CSRF token to send on POST. Defaults to the `csrf-token` meta tag. */
	csrfToken?: string;
	/** Override fetch implementation (useful in tests). */
	fetch?: typeof fetch;
}

const DEFAULT_BASE_URL = '/api/performance';

function resolveCsrfToken(): string | undefined {
	if ( typeof document === 'undefined' ) {
		return undefined;
	}
	const meta = document.querySelector<HTMLMetaElement>( 'meta[name="csrf-token"]' );
	return meta?.content ?? undefined;
}

function normaliseBaseUrl( raw: string ): string {
	return raw.replace( /\/+$/, '' );
}

function resolveFetch( override?: typeof fetch ): typeof fetch {
	if ( override ) {
		return override;
	}
	if ( typeof globalThis !== 'undefined' && 'function' === typeof globalThis.fetch ) {
		return globalThis.fetch.bind( globalThis ) as typeof fetch;
	}
	return ( () => Promise.reject( new Error( 'fetch is not available' ) ) ) as typeof fetch;
}

function buildHeaders( csrfToken?: string ): Record<string, string> {
	const headers: Record<string, string> = {
		Accept: 'application/json',
		'X-Requested-With': 'XMLHttpRequest',
	};
	if ( csrfToken ) {
		headers[ 'X-CSRF-TOKEN' ] = csrfToken;
	}
	return headers;
}

function buildQueryString( params: Record<string, unknown> ): string {
	const search = new URLSearchParams();
	Object.entries( params ).forEach( ( [ key, value ] ) => {
		if ( undefined === value || null === value || '' === value ) {
			return;
		}
		search.set( key, String( value ) );
	} );
	const encoded = search.toString();
	return '' === encoded ? '' : `?${ encoded }`;
}

export class PerformanceClient {
	private readonly baseUrl: string;

	private readonly csrfToken: string | undefined;

	private readonly fetchImpl: typeof fetch;

	constructor( options: PerformanceClientOptions = {} ) {
		this.baseUrl = normaliseBaseUrl( options.baseUrl ?? DEFAULT_BASE_URL );
		this.csrfToken = options.csrfToken ?? resolveCsrfToken();
		this.fetchImpl = resolveFetch( options.fetch );
	}

	async getDashboard( range: DateRangeKey = '7d' ): Promise<DashboardPayload> {
		return this.get<DashboardPayload>( `/admin/dashboard${ buildQueryString( { range } ) }` );
	}

	async getChart(
		options: { metrics?: string[]; range?: ChartRangeKey; showThreshold?: boolean; type?: 'line' | 'bar' | 'area' } = {},
	): Promise<ChartPayload> {
		const query = buildQueryString( {
			metrics: options.metrics?.join( ',' ),
			range: options.range,
			show_threshold: undefined === options.showThreshold ? undefined : options.showThreshold ? 1 : 0,
			type: options.type,
		} );
		return this.get<ChartPayload>( `/admin/chart${ query }` );
	}

	async getCache(): Promise<CachePayload> {
		return this.get<CachePayload>( '/admin/cache' );
	}

	async runCacheAction(
		action: 'flush' | 'warm' | 'invalidate-key' | 'invalidate-tag',
		payload: { key?: string; tag?: string } = {},
	): Promise<CacheActionResult> {
		return this.post<CacheActionResult>( '/admin/cache/actions', { action, ...payload } );
	}

	async getQueries( options: QueriesQuery = {} ): Promise<QueriesPayload> {
		const query = buildQueryString( {
			range: options.range,
			route: options.route,
			min_time_ms: options.min_time_ms,
			sort: options.sort,
		} );
		return this.get<QueriesPayload>( `/admin/queries${ query }` );
	}

	/**
	 * Synchronous URL builder. Intentionally NOT async so Safari's
	 * popup blocker recognises the immediate `window.open(client.exportQueriesCsvUrl(...))`
	 * call as gesture-originated. An intervening `await` would defer
	 * `window.open` to a microtask and the tab would be suppressed.
	 */
	exportQueriesCsvUrl( options: QueriesQuery = {} ): string {
		return `${ this.baseUrl }/admin/queries/export${ buildQueryString( {
			range: options.range,
			route: options.route,
			min_time_ms: options.min_time_ms,
			sort: options.sort,
		} ) }`;
	}

	async getRecommendations( range: DateRangeKey = '7d' ): Promise<RecommendationsPayload> {
		return this.get<RecommendationsPayload>( `/admin/recommendations${ buildQueryString( { range } ) }` );
	}

	async runRecommendationAction(
		action: 'apply' | 'dismiss' | 'reset',
		payload: { id?: string } = {},
	): Promise<RecommendationActionResult> {
		return this.post<RecommendationActionResult>( '/admin/recommendations/actions', {
			action,
			...payload,
		} );
	}

	/**
	 * Reports a single Web Vital sample to the metrics ingest endpoint.
	 *
	 * Returns the parsed response body. The endpoint returns `{success:false, reason}`
	 * for sampled-out or disabled cases with a 200, so callers should inspect the
	 * body rather than assume success from a non-throwing call.
	 */
	async reportVital( vital: WebVitalReport ): Promise<{ success: boolean; reason?: string }> {
		return this.post<{ success: boolean; reason?: string }>( '/metrics', vital );
	}

	async suggestQueryInsight( input: QueryInsightInput ): Promise<QueryInsight> {
		const response = await this.postAi<QueryInsight>(
			'/ai/query-insight',
			'performance.query_insight',
			input as unknown as Record<string, unknown>,
		);
		return response.data;
	}

	async suggestOptimization( input: OptimizationSuggestionInput ): Promise<OptimizationSuggestion> {
		const response = await this.postAi<OptimizationSuggestion>(
			'/ai/optimization-suggestion',
			'performance.optimization_suggestion',
			input as unknown as Record<string, unknown>,
		);
		return response.data;
	}

	private async get<T>( path: string ): Promise<T> {
		const response = await this.fetchImpl( `${ this.baseUrl }${ path }`, {
			method: 'GET',
			credentials: 'same-origin',
			headers: buildHeaders( this.csrfToken ),
		} );
		if ( ! response.ok ) {
			throw new Error( `Performance API GET ${ path } failed: ${ response.status }` );
		}
		return ( await response.json() ) as T;
	}

	private async post<T>( path: string, body: Record<string, unknown> ): Promise<T> {
		const response = await this.fetchImpl( `${ this.baseUrl }${ path }`, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				...buildHeaders( this.csrfToken ),
				'Content-Type': 'application/json',
			},
			body: JSON.stringify( body ),
		} );
		if ( ! response.ok ) {
			throw new Error( `Performance API POST ${ path } failed: ${ response.status }` );
		}
		return ( await response.json() ) as T;
	}

	private async postAi<T>(
		path: string,
		featureKey: AiFeatureKey,
		body: Record<string, unknown>,
	): Promise<AiAgentResponse<T>> {
		const response = await this.fetchImpl( `${ this.baseUrl }${ path }`, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				...buildHeaders( this.csrfToken ),
				'Content-Type': 'application/json',
			},
			body: JSON.stringify( body ),
		} );

		let parsed: unknown = null;
		try {
			parsed = await response.json();
		} catch {
			// Non-JSON body (e.g. 5xx HTML error page) — swallow so the
			// thrown PerformanceAiError still carries the status code.
		}

		if ( ! response.ok ) {
			const message = 'object' === typeof parsed && null !== parsed && 'string' === typeof ( parsed as { message?: unknown } ).message
				? ( parsed as { message: string } ).message
				: `Performance AI request failed: ${ response.status }`;
			throw new PerformanceAiError( message, response.status, featureKey );
		}

		// A 2xx with a null / non-object / missing-`data` body is still a
		// broken response — surface it as a PerformanceAiError so callers'
		// existing catch branch fires instead of letting `response.data`
		// throw an uncaught TypeError one frame away.
		if ( null === parsed || 'object' !== typeof parsed || ! ( 'data' in parsed ) ) {
			throw new PerformanceAiError(
				`Performance AI request returned a malformed response (${ response.status }).`,
				response.status,
				featureKey,
			);
		}

		return parsed as AiAgentResponse<T>;
	}
}

let defaultClient: PerformanceClient | null = null;

export function getPerformanceClient( options?: PerformanceClientOptions ): PerformanceClient {
	if ( null === defaultClient ) {
		defaultClient = new PerformanceClient( options );
	}
	return defaultClient;
}

export function resetPerformanceClient(): void {
	defaultClient = null;
}
