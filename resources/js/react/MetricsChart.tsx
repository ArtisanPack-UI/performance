/**
 * MetricsChart — React port of the `MetricsChart` Livewire component.
 *
 * Loads the chart payload from the admin API and renders a simple
 * table + `<canvas data-metrics-chart>` shell so the bundled
 * `metrics-chart.js` bootstrap (Chart.js) can wire the actual chart.
 * When Chart.js isn't loaded the table alone is still readable.
 *
 * @since 1.0.0
 */

import { useMemo, useState, type JSX } from 'react';
import type { ChartPayload, ChartRangeKey } from '../performance';
import { useAsyncPayload, usePerformance, type UsePerformanceOptions } from './usePerformance';

export interface MetricsChartProps extends UsePerformanceOptions {
	metrics?: string[];
	initialRange?: ChartRangeKey;
	initialType?: 'line' | 'bar' | 'area';
	showThreshold?: boolean;
	className?: string;
	labels?: {
		range?: string;
		'7d'?: string;
		'30d'?: string;
		'90d'?: string;
		fallbackTitle?: string;
	};
}

const RANGES: ChartRangeKey[] = [ '7d', '30d', '90d' ];

export function MetricsChart( props: MetricsChartProps ): JSX.Element {
	const {
		metrics = [ 'LCP' ],
		initialRange = '7d',
		initialType = 'line',
		showThreshold = true,
		className,
		labels,
		...hookOptions
	} = props;

	const { loadChart } = usePerformance( hookOptions );
	const [ range, setRange ] = useState<ChartRangeKey>( initialRange );

	const { data, loading, error } = useAsyncPayload<ChartPayload, [ ChartRangeKey ]>(
		() => loadChart( { metrics, range, showThreshold, type: initialType } ),
		[ range ],
	);

	const payloadJson = useMemo( () => ( data ? JSON.stringify( data ) : '' ), [ data ] );

	return (
		<div className={ [ 'performance-metrics-chart', className ].filter( Boolean ).join( ' ' ) } data-testid="performance-metrics-chart">
			<div className="performance-metrics-chart__range" role="group" aria-label={ labels?.range ?? 'Date range' }>
				{ RANGES.map( ( key ) => (
					<button
						key={ key }
						type="button"
						aria-pressed={ range === key }
						onClick={ () => setRange( key ) }
					>
						{ labels?.[ key ] ?? key }
					</button>
				) ) }
			</div>

			{ error && <p role="alert">{ error.message }</p> }
			{ loading && <p>Loading…</p> }

			{ data && (
				<>
					<div className="performance-metrics-chart__canvas" data-metrics-chart data-chart-payload={ payloadJson }>
						<canvas />
					</div>
					<details>
						<summary>{ labels?.fallbackTitle ?? 'Data' }</summary>
						<table>
							<thead>
								<tr>
									<th>Date</th>
									{ data.datasets.map( ( ds ) => (
										<th key={ ds.metric }>{ ds.metric }</th>
									) ) }
								</tr>
							</thead>
							<tbody>
								{ data.labels.map( ( label, i ) => (
									<tr key={ label }>
										<th scope="row">{ label }</th>
										{ data.datasets.map( ( ds ) => (
											<td key={ ds.metric }>{ null === ds.values[ i ] ? '—' : ds.values[ i ] }</td>
										) ) }
									</tr>
								) ) }
							</tbody>
						</table>
					</details>
				</>
			) }
		</div>
	);
}
