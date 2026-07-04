/**
 * RecommendationsPanel — React port of the `RecommendationsPanel` Livewire component.
 *
 * @since 1.0.0
 */

import { useCallback, useEffect, useState, type JSX } from 'react';
import type { DateRangeKey, Recommendation, RecommendationsPayload } from '../performance';
import { useAsyncPayload, usePerformance, type UsePerformanceOptions } from './usePerformance';

export interface RecommendationsPanelProps extends UsePerformanceOptions {
	initialRange?: DateRangeKey;
	className?: string;
	onGenerateIndexMigration?: ( payload: { table: string; columns: string[] } ) => void;
	onNavigate?: ( tab: string ) => void;
}

export function RecommendationsPanel( props: RecommendationsPanelProps ): JSX.Element {
	const {
		initialRange = '7d',
		className,
		onGenerateIndexMigration,
		onNavigate,
		...hookOptions
	} = props;

	const { runRecommendationAction, loadRecommendations } = usePerformance( hookOptions );
	const [ range, setRange ] = useState<DateRangeKey>( initialRange );
	const [ status, setStatus ] = useState<{ message: string; isError: boolean } | null>( null );
	const [ working, setWorking ] = useState( false );

	// Sync local range with the parent dashboard's range picker.
	useEffect( () => {
		setRange( initialRange );
	}, [ initialRange ] );

	const { data, loading, error, reload } = useAsyncPayload<RecommendationsPayload, [ DateRangeKey ]>(
		() => loadRecommendations( range ),
		[ range ],
	);

	const runAction = useCallback(
		async ( action: 'apply' | 'dismiss' | 'reset', payload: { id?: string } = {} ) => {
			// Capture the recommendation BEFORE the network call so a
			// racing reload can't drop the navigation/migration callback.
			const recommendation = 'apply' === action && payload.id
				? data?.items.find( ( item ) => item.id === payload.id )
				: undefined;

			setWorking( true );
			try {
				const result = await runRecommendationAction( action, payload );
				setStatus( { message: result.message, isError: result.is_error } );
				if ( recommendation ) {
					if ( 'generate-index-migration' === recommendation.action ) {
						const p = recommendation.action_payload ?? {};
						onGenerateIndexMigration?.( {
							table: String( p.table ?? '' ),
							columns: Array.isArray( p.columns ) ? ( p.columns as string[] ) : [],
						} );
					} else if ( 'view-query-analyzer' === recommendation.action ) {
						onNavigate?.( 'queries' );
					}
				}
				await reload();
			} catch ( err ) {
				setStatus( {
					message: err instanceof Error ? err.message : String( err ),
					isError: true,
				} );
			} finally {
				setWorking( false );
			}
		},
		[ data?.items, onGenerateIndexMigration, onNavigate, reload, runRecommendationAction ],
	);

	return (
		<div className={ [ 'performance-recommendations', className ].filter( Boolean ).join( ' ' ) } data-testid="recommendations-panel">
			{ status && (
				<div role={ status.isError ? 'alert' : 'status' } className={ status.isError ? 'is-error' : '' }>
					{ status.message }
				</div>
			) }
			{ error && <p role="alert">{ error.message }</p> }
			{ loading && <p>Loading…</p> }
			{ data && (
				<>
					{ data.dismissed.length > 0 && (
						<button type="button" onClick={ () => void runAction( 'reset' ) } disabled={ working }>
							Restore dismissed ({ data.dismissed.length })
						</button>
					) }
					<ul>
						{ data.items.map( ( item: Recommendation ) => (
							<li key={ item.id } className={ `priority-${ item.priority }` }>
								<h4>
									<span className={ `priority-badge priority-badge--${ item.priority }` }>{ item.priority }</span>
									{ item.title }
								</h4>
								{ item.description && <p>{ item.description }</p> }
								{ item.manual_steps && item.manual_steps.length > 0 && (
									<details>
										<summary>Manual steps</summary>
										<ol>
											{ item.manual_steps.map( ( step, i ) => <li key={ i }>{ step }</li> ) }
										</ol>
									</details>
								) }
								<div>
									{ item.action && (
										<button type="button" onClick={ () => void runAction( 'apply', { id: item.id } ) } disabled={ working }>
											Apply fix
										</button>
									) }
									<button type="button" onClick={ () => void runAction( 'dismiss', { id: item.id } ) } disabled={ working }>
										Dismiss
									</button>
								</div>
							</li>
						) ) }
					</ul>
				</>
			) }
		</div>
	);
}
