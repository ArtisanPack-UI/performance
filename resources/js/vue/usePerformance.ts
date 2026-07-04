/**
 * usePerformance — Vue composable wrapping PerformanceClient.
 *
 * @since 1.0.0
 */

import { onBeforeUnmount, onMounted, ref, watch, type Ref, type WatchSource } from 'vue';
import {
	type CacheActionResult,
	type CachePayload,
	type ChartPayload,
	type ChartRangeKey,
	type DashboardPayload,
	type DateRangeKey,
	getPerformanceClient,
	type PerformanceClient,
	type PerformanceClientOptions,
	type QueriesPayload,
	type QueriesQuery,
	type RecommendationActionResult,
	type RecommendationsPayload,
} from '../performance';

export interface UsePerformanceOptions extends PerformanceClientOptions {
	client?: PerformanceClient;
}

export interface UsePerformanceResult {
	client: PerformanceClient;
	loadDashboard: ( range?: DateRangeKey ) => Promise<DashboardPayload>;
	loadChart: ( options?: {
		metrics?: string[];
		range?: ChartRangeKey;
		showThreshold?: boolean;
		type?: 'line' | 'bar' | 'area';
	} ) => Promise<ChartPayload>;
	loadCache: () => Promise<CachePayload>;
	runCacheAction: (
		action: 'flush' | 'warm' | 'invalidate-key' | 'invalidate-tag',
		payload?: { key?: string; tag?: string },
	) => Promise<CacheActionResult>;
	loadQueries: ( options?: QueriesQuery ) => Promise<QueriesPayload>;
	loadRecommendations: ( range?: DateRangeKey ) => Promise<RecommendationsPayload>;
	runRecommendationAction: (
		action: 'apply' | 'dismiss' | 'reset',
		payload?: { id?: string },
	) => Promise<RecommendationActionResult>;
}

export function usePerformance( options: UsePerformanceOptions = {} ): UsePerformanceResult {
	const client: PerformanceClient = options.client ?? getPerformanceClient( options );

	return {
		client,
		loadDashboard: ( range ) => client.getDashboard( range ),
		loadChart: ( o ) => client.getChart( o ),
		loadCache: () => client.getCache(),
		runCacheAction: ( action, payload ) => client.runCacheAction( action, payload ?? {} ),
		loadQueries: ( o ) => client.getQueries( o ),
		loadRecommendations: ( range ) => client.getRecommendations( range ),
		runRecommendationAction: ( action, payload ) => client.runRecommendationAction( action, payload ?? {} ),
	};
}

export interface AsyncState<T> {
	data: Ref<T | null>;
	loading: Ref<boolean>;
	error: Ref<Error | null>;
	reload: () => Promise<void>;
}

/**
 * Small helper mirroring React's useAsyncPayload for parity across the two ports.
 *
 * Runs `loader` on mount and whenever any `WatchSource` in `deps` changes.
 */
export function useAsyncPayload<T>(
	loader: () => Promise<T>,
	deps: WatchSource[] = [],
): AsyncState<T> {
	const data = ref<T | null>( null ) as Ref<T | null>;
	const loading = ref<boolean>( true );
	const error = ref<Error | null>( null );
	let cancelled = false;

	async function reload(): Promise<void> {
		loading.value = true;
		error.value = null;
		try {
			const next = await loader();
			if ( ! cancelled ) {
				data.value = next;
			}
		} catch ( err: unknown ) {
			if ( ! cancelled ) {
				error.value = err instanceof Error ? err : new Error( String( err ) );
			}
		} finally {
			if ( ! cancelled ) {
				loading.value = false;
			}
		}
	}

	onMounted( () => {
		void reload();
	} );

	if ( deps.length > 0 ) {
		watch( deps, () => {
			void reload();
		} );
	}

	onBeforeUnmount( () => {
		cancelled = true;
	} );

	return { data, loading, error, reload };
}
