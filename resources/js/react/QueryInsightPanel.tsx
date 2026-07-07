/**
 * QueryInsightPanel — React trigger for the PerformanceInsightAgent.
 *
 * Package-local (not exported through @artisanpack-ui/react) because the
 * agent's input shape and rendering are specific to the performance
 * package's domain model.
 *
 * @since 1.1.0
 */

import { type FC, useState } from 'react';

import {
	getPerformanceClient,
	PerformanceAiError,
	type PerformanceClient,
	type PerformanceClientOptions,
	type QueryInsight,
	type QueryInsightInput,
} from '../performance';

export interface QueryInsightPanelProps {
	/** Optional shared client. Falls back to the singleton. */
	client?: PerformanceClient;
	/** Options forwarded to `getPerformanceClient` when no client is passed. */
	clientOptions?: PerformanceClientOptions;
	/** Pre-populate the query textarea. */
	initialQuery?: string;
	/** Pre-populate the EXPLAIN plan textarea. */
	initialExplain?: string;
	/** Pre-populate the schema hint textarea (JSON or plain text). */
	initialSchema?: string;
	/** Pre-populate the connection driver input (e.g. `mysql`). */
	initialConnection?: string;
	/** Pre-populate the observed duration in ms. */
	initialTimeMs?: number | null;
	/** Called with the fresh insight after a successful analyze. */
	onInsight?: ( insight: QueryInsight ) => void;
}

function parseSchema( raw: string ): QueryInsightInput['schema'] {
	const trimmed = raw.trim();
	if ( '' === trimmed ) {
		return null;
	}
	try {
		const parsed = JSON.parse( trimmed );
		if ( 'object' === typeof parsed && null !== parsed ) {
			return parsed as QueryInsightInput['schema'];
		}
	} catch {
		// Not JSON — pass the raw text through so the agent can still use
		// it as a plain-text schema hint, matching the "JSON or text"
		// placeholder the panel promises.
	}
	return trimmed;
}

export const QueryInsightPanel: FC<QueryInsightPanelProps> = ( {
	client,
	clientOptions,
	initialQuery = '',
	initialExplain = '',
	initialSchema = '',
	initialConnection = '',
	initialTimeMs = null,
	onInsight,
} ) => {
	const resolvedClient = client ?? getPerformanceClient( clientOptions );

	const [query, setQuery] = useState<string>( initialQuery );
	const [explain, setExplain] = useState<string>( initialExplain );
	const [schema, setSchema] = useState<string>( initialSchema );
	const [connection, setConnection] = useState<string>( initialConnection );
	const [timeMs, setTimeMs] = useState<number | null>( initialTimeMs );
	const [insight, setInsight] = useState<QueryInsight | null>( null );
	const [error, setError] = useState<string | null>( null );
	const [isLoading, setIsLoading] = useState<boolean>( false );

	const disabled = isLoading || '' === query.trim();

	const handleAnalyze = async (): Promise<void> => {
		setError( null );
		setInsight( null );
		setIsLoading( true );

		try {
			const parsedSchema = parseSchema( schema );
			const explainValue = '' === explain.trim() ? null : explain;
			const connectionValue = '' === connection.trim() ? null : connection.trim();

			const result = await resolvedClient.suggestQueryInsight( {
				query,
				explain: explainValue,
				schema: parsedSchema,
				time_ms: timeMs,
				connection: connectionValue,
			} );

			setInsight( result );
			onInsight?.( result );
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
		<div className="performance-ai-panel" data-feature="performance.query_insight">
			<div className="performance-ai-panel__fields">
				<label className="performance-ai-panel__label" htmlFor="query-insight-query-react">
					Query
				</label>
				<textarea
					id="query-insight-query-react"
					className="performance-ai-panel__textarea"
					rows={ 5 }
					value={ query }
					onChange={ ( event ) => setQuery( event.target.value ) }
					placeholder="SELECT * FROM …"
				/>

				<label className="performance-ai-panel__label" htmlFor="query-insight-explain-react">
					EXPLAIN plan (optional)
				</label>
				<textarea
					id="query-insight-explain-react"
					className="performance-ai-panel__textarea"
					rows={ 4 }
					value={ explain }
					onChange={ ( event ) => setExplain( event.target.value ) }
					placeholder="EXPLAIN output …"
				/>

				<label className="performance-ai-panel__label" htmlFor="query-insight-schema-react">
					Relevant schema (optional, JSON or text)
				</label>
				<textarea
					id="query-insight-schema-react"
					className="performance-ai-panel__textarea"
					rows={ 4 }
					value={ schema }
					onChange={ ( event ) => setSchema( event.target.value ) }
					placeholder='{ "articles": { "id": "bigint", "slug": "varchar(191)" } }'
				/>

				<div className="performance-ai-panel__meta">
					<label className="performance-ai-panel__label" htmlFor="query-insight-connection-react">
						Connection
					</label>
					<input
						id="query-insight-connection-react"
						className="performance-ai-panel__input"
						type="text"
						value={ connection }
						onChange={ ( event ) => setConnection( event.target.value ) }
						placeholder="mysql"
					/>

					<label className="performance-ai-panel__label" htmlFor="query-insight-time-react">
						Observed duration (ms)
					</label>
					<input
						id="query-insight-time-react"
						className="performance-ai-panel__input"
						type="number"
						step="0.01"
						value={ null === timeMs ? '' : timeMs }
						onChange={ ( event ) => {
							const raw = event.target.value;
							setTimeMs( '' === raw ? null : Number( raw ) );
						} }
						placeholder="120.5"
					/>
				</div>
			</div>

			<button
				type="button"
				className="performance-ai-panel__button"
				disabled={ disabled }
				onClick={ () => {
					void handleAnalyze();
				} }
			>
				{ isLoading ? 'Analyzing…' : 'Analyze query' }
			</button>

			{ error && (
				<p className="performance-ai-panel__error" role="alert">
					{ error }
				</p>
			) }

			{ insight && (
				<div className="performance-ai-panel__result">
					{ insight.summary && <p className="performance-ai-panel__summary">{ insight.summary }</p> }

					{ insight.bottlenecks.length > 0 && (
						<>
							<h4 className="performance-ai-panel__heading">Bottlenecks</h4>
							<ol className="performance-ai-panel__list">
								{ insight.bottlenecks.map( ( bottleneck, index ) => (
									<li key={ `bottleneck-${ index }` }>{ bottleneck }</li>
								) ) }
							</ol>
						</>
					) }

					{ insight.suggested_indexes.length > 0 && (
						<>
							<h4 className="performance-ai-panel__heading">Suggested indexes</h4>
							<ul className="performance-ai-panel__list">
								{ insight.suggested_indexes.map( ( index, key ) => (
									<li key={ `index-${ key }` }>
										<code>
											{ index.table }({ index.columns.join( ', ' ) })
										</code>
										{ index.rationale && (
											<span className="performance-ai-panel__rationale">{ index.rationale }</span>
										) }
									</li>
								) ) }
							</ul>
						</>
					) }

					{ insight.rewrites.length > 0 && (
						<>
							<h4 className="performance-ai-panel__heading">Suggested rewrites</h4>
							<ul className="performance-ai-panel__list">
								{ insight.rewrites.map( ( rewrite, index ) => (
									<li key={ `rewrite-${ index }` }>
										<div className="performance-ai-panel__rewrite">
											<div className="performance-ai-panel__rewrite-before">
												<span className="performance-ai-panel__rewrite-label">Before</span>
												<code>{ rewrite.original }</code>
											</div>
											<div className="performance-ai-panel__rewrite-after">
												<span className="performance-ai-panel__rewrite-label">After</span>
												<code>{ rewrite.suggested }</code>
											</div>
										</div>
										{ rewrite.rationale && (
											<span className="performance-ai-panel__rationale">{ rewrite.rationale }</span>
										) }
									</li>
								) ) }
							</ul>
						</>
					) }

					{ insight.caveats.length > 0 && (
						<>
							<h4 className="performance-ai-panel__heading">Caveats</h4>
							<ul className="performance-ai-panel__list performance-ai-panel__list--muted">
								{ insight.caveats.map( ( caveat, index ) => (
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

export default QueryInsightPanel;
