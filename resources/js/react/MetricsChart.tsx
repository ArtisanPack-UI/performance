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

import { useEffect, useMemo, useRef, useState, type JSX } from 'react';
import { renderPerfChart } from '../metrics-chart.js';
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

	// Keep local `range` in sync when the parent updates `initialRange`
	// (dashboard-level range picker). Without this, once the child
	// mounts the initial range snapshot is frozen.
	useEffect( () => {
		setRange( initialRange );
	}, [ initialRange ] );

	// Stable string key for the metrics list so the loader dep tuple
	// doesn't churn on every render from a fresh array literal.
	const metricsKey = metrics.join( ',' );

	const { data, loading, error } = useAsyncPayload<ChartPayload, [ ChartRangeKey, string, boolean, string ]>(
		() => loadChart( { metrics, range, showThreshold, type: initialType } ),
		[ range, metricsKey, showThreshold, initialType ],
	);

	const payloadJson = useMemo( () => ( data ? JSON.stringify( data ) : '' ), [ data ] );
	const canvasContainerRef = useRef<HTMLDivElement | null>( null );

	// Trigger the Chart.js bootstrap whenever the payload changes.
	// `metrics-chart.js` auto-boots on DOMContentLoaded, which is before
	// React mounts the component; without this hook the canvas is never
	// wired and only the fallback data table renders.
	useEffect( () => {
		if ( null !== canvasContainerRef.current && '' !== payloadJson ) {
			renderPerfChart( canvasContainerRef.current );
		}
	}, [ payloadJson ] );

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
					<div ref={ canvasContainerRef } className="performance-metrics-chart__canvas" data-metrics-chart data-chart-payload={ payloadJson }>
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
