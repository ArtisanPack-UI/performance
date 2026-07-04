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

	async exportQueriesCsvUrl( options: QueriesQuery = {} ): Promise<string> {
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
