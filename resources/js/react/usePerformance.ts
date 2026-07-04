/**
 * usePerformance — React hook wrapping PerformanceClient.
 *
 * Loads any of the admin payloads (dashboard/chart/cache/queries/recommendations)
 * on demand and caches them in local state. Companion components use this
 * to keep their fetch/action wiring identical to the Vue side.
 *
 * @since 1.0.0
 */

import { useCallback, useEffect, useRef, useState } from 'react';

// Monotonic per-hook-instance request id used to drop late responses
// whose issuing render is no longer current.
type RequestId = number;
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
	type QuerySortKey,
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
	const clientRef = useRef<PerformanceClient | null>( null );
	if ( null === clientRef.current ) {
		clientRef.current = options.client ?? getPerformanceClient( options );
	}
	const client = clientRef.current;

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
	data: T | null;
	loading: boolean;
	error: Error | null;
	reload: () => Promise<void>;
}

/**
 * Small helper that runs a load function on mount + when dependencies change.
 *
 * Not exported at the barrel — used internally by dashboard components.
 */
export function useAsyncPayload<T, Deps extends readonly unknown[]>(
	loader: () => Promise<T>,
	deps: Deps,
): AsyncState<T> {
	const [ data, setData ] = useState<T | null>( null );
	const [ loading, setLoading ] = useState<boolean>( true );
	const [ error, setError ] = useState<Error | null>( null );

	// Every launch bumps this counter; late responses whose id is no
	// longer the current one are discarded. Prevents rapid-click races
	// where an older response arrives after a newer one.
	const requestIdRef = useRef<RequestId>( 0 );

	const runLoader = useCallback( ( sourceLoader: () => Promise<T> ): void => {
		const requestId = ++requestIdRef.current;
		setLoading( true );
		setError( null );
		sourceLoader()
			.then( ( next ) => {
				if ( requestId === requestIdRef.current ) {
					setData( next );
				}
			} )
			.catch( ( err: unknown ) => {
				if ( requestId === requestIdRef.current ) {
					setError( err instanceof Error ? err : new Error( String( err ) ) );
				}
			} )
			.finally( () => {
				if ( requestId === requestIdRef.current ) {
					setLoading( false );
				}
			} );
	}, [] );

	const reload = useCallback( async (): Promise<void> => {
		runLoader( loader );
	// eslint-disable-next-line react-hooks/exhaustive-deps
	}, deps );

	useEffect( () => {
		runLoader( loader );
		return () => {
			// Invalidate any in-flight request tied to this render.
			requestIdRef.current++;
		};
	// eslint-disable-next-line react-hooks/exhaustive-deps
	}, deps );

	return { data, loading, error, reload };
}
