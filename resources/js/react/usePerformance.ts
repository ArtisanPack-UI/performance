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

	const reload = useCallback( async (): Promise<void> => {
		setLoading( true );
		setError( null );
		try {
			const next = await loader();
			setData( next );
		} catch ( err: unknown ) {
			setError( err instanceof Error ? err : new Error( String( err ) ) );
		} finally {
			setLoading( false );
		}
	// eslint-disable-next-line react-hooks/exhaustive-deps
	}, deps );

	useEffect( () => {
		let cancelled = false;
		setLoading( true );
		setError( null );
		loader()
			.then( ( next ) => {
				if ( ! cancelled ) {
					setData( next );
				}
			} )
			.catch( ( err: unknown ) => {
				if ( ! cancelled ) {
					setError( err instanceof Error ? err : new Error( String( err ) ) );
				}
			} )
			.finally( () => {
				if ( ! cancelled ) {
					setLoading( false );
				}
			} );
		return () => {
			cancelled = true;
		};
	// eslint-disable-next-line react-hooks/exhaustive-deps
	}, deps );

	return { data, loading, error, reload };
}
