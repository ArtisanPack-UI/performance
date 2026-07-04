/**
 * QueryAnalyzer — React port of the `QueryAnalyzer` Livewire component.
 *
 * @since 1.0.0
 */

import { useCallback, useEffect, useState, type JSX } from 'react';
import type { DateRangeKey, QueriesPayload, QuerySortKey } from '../performance';
import { useAsyncPayload, usePerformance, type UsePerformanceOptions } from './usePerformance';

export interface QueryAnalyzerProps extends UsePerformanceOptions {
	initialRange?: DateRangeKey;
	initialRoute?: string;
	initialMinTimeMs?: number;
	initialSort?: QuerySortKey;
	className?: string;
}

const RANGES: DateRangeKey[] = [ '24h', '7d', '30d', '90d' ];

export function QueryAnalyzer( props: QueryAnalyzerProps ): JSX.Element {
	const {
		initialRange = '7d',
		initialRoute = '',
		initialMinTimeMs = 0,
		initialSort = 'time',
		className,
		...hookOptions
	} = props;

	const { client, loadQueries } = usePerformance( hookOptions );
	const [ range, setRange ] = useState<DateRangeKey>( initialRange );
	const [ route, setRoute ] = useState<string>( initialRoute );
	const [ minTimeMs, setMinTimeMs ] = useState<number>( initialMinTimeMs );
	const [ sort, setSort ] = useState<QuerySortKey>( initialSort );
	const [ expanded, setExpanded ] = useState<string | null>( null );

	// Sync local range with the parent dashboard's range picker.
	useEffect( () => {
		setRange( initialRange );
	}, [ initialRange ] );

	const { data, loading, error, reload } = useAsyncPayload<QueriesPayload, [ DateRangeKey, string, number, QuerySortKey ]>(
		() => loadQueries( { range, route: '' === route ? undefined : route, min_time_ms: minTimeMs, sort } ),
		[ range, route, minTimeMs, sort ],
	);

	const exportCsv = useCallback( (): void => {
		// Called directly from the button click handler — no `await` so
		// the popup blocker sees a gesture-originated `window.open`.
		const url = client.exportQueriesCsvUrl( {
			range,
			route: '' === route ? undefined : route,
			min_time_ms: minTimeMs,
			sort,
		} );
		window.open( url, '_blank' );
	}, [ client, range, route, minTimeMs, sort ] );

	return (
		<div className={ [ 'performance-query-analyzer', className ].filter( Boolean ).join( ' ' ) } data-testid="query-analyzer">
			<div className="performance-query-analyzer__toolbar">
				<label>
					Range
					<select value={ range } onChange={ ( e ) => setRange( e.target.value as DateRangeKey ) }>
						{ RANGES.map( ( key ) => <option key={ key } value={ key }>{ key }</option> ) }
					</select>
				</label>
				<label>
					Route
					<select value={ route } onChange={ ( e ) => setRoute( e.target.value ) }>
						<option value="">All routes</option>
						{ data?.available_routes.map( ( r ) => <option key={ r } value={ r }>{ r }</option> ) }
					</select>
				</label>
				<label>
					Min time (ms)
					<input
						type="number"
						min={ 0 }
						value={ minTimeMs }
						onChange={ ( e ) => setMinTimeMs( Math.max( 0, Number( e.target.value ) ) ) }
					/>
				</label>
				<div role="group" aria-label="Sort">
					<button type="button" aria-pressed={ 'time' === sort } onClick={ () => setSort( 'time' ) }>Slowest</button>
					<button type="button" aria-pressed={ 'frequency' === sort } onClick={ () => setSort( 'frequency' ) }>Most frequent</button>
				</div>
				<button type="button" onClick={ () => void reload() }>Refresh</button>
				<button type="button" onClick={ exportCsv }>Export CSV</button>
			</div>

			{ error && <p role="alert">{ error.message }</p> }
			{ loading && <p>Loading…</p> }

			{ data && (
				<table data-testid="query-analyzer-table">
					<thead>
						<tr>
							<th>Query</th>
							<th>Peak (ms)</th>
							<th>Count</th>
							<th>Route</th>
							<th>File</th>
							<th>Suggestion</th>
						</tr>
					</thead>
					<tbody>
						{ data.rows.map( ( row ) => {
							const isExpanded = expanded === row.hash;
							const preview = isExpanded || row.query.length <= 120 ? row.query : row.query.slice( 0, 120 ) + '…';
							return (
								<tr key={ row.hash }>
									<td>
										<code>{ preview }</code>
										{ row.query.length > 120 && (
											<button type="button" onClick={ () => setExpanded( isExpanded ? null : row.hash ) }>
												{ isExpanded ? 'Hide' : 'Show full' }
											</button>
										) }
									</td>
									<td>{ row.peak_time_ms.toFixed( 1 ) }</td>
									<td>{ row.occurrences }</td>
									<td>{ row.route ?? '—' }</td>
									<td>{ row.file ?? '—' }{ row.line ? `:${ row.line }` : '' }</td>
									<td>{ row.suggestion ?? '' }</td>
								</tr>
							);
						} ) }
					</tbody>
				</table>
			) }
		</div>
	);
}
