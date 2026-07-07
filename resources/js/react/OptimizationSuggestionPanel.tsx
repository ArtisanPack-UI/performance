/**
 * OptimizationSuggestionPanel — React trigger for the OptimizationSuggestionAgent.
 *
 * Consumers provide the metric batch (typically drawn from their own
 * PerformanceClient.getChart / getDashboard results) plus optional context
 * hints; the component orchestrates the agent call and renders the
 * returned focus areas, quick wins, and caveats.
 *
 * @since 1.1.0
 */

import { type FC, useState } from 'react';

import {
	getPerformanceClient,
	type OptimizationMetricRow,
	type OptimizationSuggestion,
	PerformanceAiError,
	type PerformanceClient,
	type PerformanceClientOptions,
} from '../performance';

export interface OptimizationSuggestionPanelProps {
	client?: PerformanceClient;
	clientOptions?: PerformanceClientOptions;
	/** Metrics batch to analyze. Required — pass in whatever the app aggregated. */
	metrics: OptimizationMetricRow[];
	/** Date range covered by `metrics`. */
	range: { from: string; to: string };
	initialBusinessPriority?: string;
	initialRecentChanges?: string;
	initialTrafficMix?: string;
	onSuggestion?: ( suggestion: OptimizationSuggestion ) => void;
}

export const OptimizationSuggestionPanel: FC<OptimizationSuggestionPanelProps> = ( {
	client,
	clientOptions,
	metrics,
	range,
	initialBusinessPriority = '',
	initialRecentChanges = '',
	initialTrafficMix = '',
	onSuggestion,
} ) => {
	const resolvedClient = client ?? getPerformanceClient( clientOptions );

	const [businessPriority, setBusinessPriority] = useState<string>( initialBusinessPriority );
	const [recentChanges, setRecentChanges] = useState<string>( initialRecentChanges );
	const [trafficMix, setTrafficMix] = useState<string>( initialTrafficMix );
	const [suggestion, setSuggestion] = useState<OptimizationSuggestion | null>( null );
	const [error, setError] = useState<string | null>( null );
	const [isLoading, setIsLoading] = useState<boolean>( false );

	const disabled = isLoading || 0 === metrics.length;

	const handleSuggest = async (): Promise<void> => {
		setError( null );
		setSuggestion( null );
		setIsLoading( true );

		try {
			const context: Record<string, string> = {};
			if ( '' !== businessPriority.trim() ) {
				context.business_priority = businessPriority.trim();
			}
			if ( '' !== recentChanges.trim() ) {
				context.recent_changes = recentChanges.trim();
			}
			if ( '' !== trafficMix.trim() ) {
				context.traffic_mix = trafficMix.trim();
			}

			const result = await resolvedClient.suggestOptimization( {
				range,
				metrics,
				context: 0 === Object.keys( context ).length ? undefined : context,
			} );

			setSuggestion( result );
			onSuggestion?.( result );
		} catch ( caught ) {
			if ( caught instanceof PerformanceAiError ) {
				setError( caught.message );
			} else if ( caught instanceof Error ) {
				setError( caught.message );
			} else {
				setError( 'The AI agent could not complete this request.' );
			}
		} finally {
			setIsLoading( false );
		}
	};

	return (
		<div className="performance-ai-panel" data-feature="performance.optimization_suggestion">
			<div className="performance-ai-panel__fields">
				<label className="performance-ai-panel__label" htmlFor="optimization-priority-react">
					Business priority (optional)
				</label>
				<input
					id="optimization-priority-react"
					className="performance-ai-panel__input"
					type="text"
					value={ businessPriority }
					onChange={ ( event ) => setBusinessPriority( event.target.value ) }
					placeholder="checkout > blog"
				/>

				<label className="performance-ai-panel__label" htmlFor="optimization-recent-react">
					Recent changes (optional)
				</label>
				<textarea
					id="optimization-recent-react"
					className="performance-ai-panel__textarea"
					rows={ 2 }
					value={ recentChanges }
					onChange={ ( event ) => setRecentChanges( event.target.value ) }
					placeholder="switched to Vite last week"
				/>

				<label className="performance-ai-panel__label" htmlFor="optimization-traffic-react">
					Traffic mix (optional)
				</label>
				<input
					id="optimization-traffic-react"
					className="performance-ai-panel__input"
					type="text"
					value={ trafficMix }
					onChange={ ( event ) => setTrafficMix( event.target.value ) }
					placeholder="65% mobile, 35% desktop"
				/>
			</div>

			<button
				type="button"
				className="performance-ai-panel__button"
				disabled={ disabled }
				onClick={ () => {
					void handleSuggest();
				} }
			>
				{ isLoading ? 'Analyzing…' : 'Suggest optimizations' }
			</button>

			{ error && (
				<p className="performance-ai-panel__error" role="alert">
					{ error }
				</p>
			) }

			{ suggestion && (
				<div className="performance-ai-panel__result">
					{ suggestion.summary && (
						<p className="performance-ai-panel__summary">{ suggestion.summary }</p>
					) }

					{ suggestion.focus_areas.length > 0 && (
						<>
							<h4 className="performance-ai-panel__heading">Focus areas</h4>
							<ul className="performance-ai-panel__list">
								{ suggestion.focus_areas.map( ( area, index ) => (
									<li key={ `focus-${ index }` } className="performance-ai-panel__focus-area">
										<div className="performance-ai-panel__focus-area-header">
											<strong>{ area.title }</strong>
											<span
												className={ `performance-ai-panel__tag performance-ai-panel__tag--impact-${ area.impact }` }
											>
												Impact: { area.impact }
											</span>
											<span
												className={ `performance-ai-panel__tag performance-ai-panel__tag--effort-${ area.effort }` }
											>
												Effort: { area.effort }
											</span>
										</div>

										{ area.routes.length > 0 && (
											<p className="performance-ai-panel__routes">
												Routes:{ ' ' }
												{ area.routes.map( ( route, routeIndex ) => (
													<span key={ `route-${ index }-${ routeIndex }` }>
														<code>{ route }</code>
														{ routeIndex < area.routes.length - 1 ? ', ' : '' }
													</span>
												) ) }
											</p>
										) }

										{ area.rationale && (
											<p className="performance-ai-panel__rationale">{ area.rationale }</p>
										) }

										{ area.actions.length > 0 && (
											<ul className="performance-ai-panel__actions">
												{ area.actions.map( ( action, actionIndex ) => (
													<li key={ `action-${ index }-${ actionIndex }` }>{ action }</li>
												) ) }
											</ul>
										) }
									</li>
								) ) }
							</ul>
						</>
					) }

					{ suggestion.quick_wins.length > 0 && (
						<>
							<h4 className="performance-ai-panel__heading">Quick wins</h4>
							<ul className="performance-ai-panel__list">
								{ suggestion.quick_wins.map( ( win, index ) => (
									<li key={ `win-${ index }` }>{ win }</li>
								) ) }
							</ul>
						</>
					) }

					{ suggestion.caveats.length > 0 && (
						<>
							<h4 className="performance-ai-panel__heading">Caveats</h4>
							<ul className="performance-ai-panel__list performance-ai-panel__list--muted">
								{ suggestion.caveats.map( ( caveat, index ) => (
									<li key={ `caveat-${ index }` }>{ caveat }</li>
								) ) }
							</ul>
						</>
					) }
				</div>
			) }
		</div>
	);
};

export default OptimizationSuggestionPanel;
