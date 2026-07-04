/**
 * PerformanceDashboard — React port of the top-level Livewire dashboard.
 *
 * Composes the range picker + tab strip + the per-tab child components.
 *
 * @since 1.0.0
 */

import { useCallback, useState, type JSX } from 'react';
import type { DashboardPayload, DateRangeKey, WebVitalStatus } from '../performance';
import { CacheManager } from './CacheManager';
import { MetricsChart } from './MetricsChart';
import { QueryAnalyzer } from './QueryAnalyzer';
import { RecommendationsPanel } from './RecommendationsPanel';
import { useAsyncPayload, usePerformance, type UsePerformanceOptions } from './usePerformance';

export type PerformanceDashboardTab = 'overview' | 'pages' | 'images' | 'cache' | 'queries' | 'recommendations';

export interface PerformanceDashboardProps extends UsePerformanceOptions {
	initialRange?: DateRangeKey;
	initialTab?: PerformanceDashboardTab;
	tabs?: PerformanceDashboardTab[];
	className?: string;
	cardClassName?: string;
}

const RANGES: DateRangeKey[] = [ '24h', '7d', '30d', '90d' ];

const DEFAULT_TABS: PerformanceDashboardTab[] = [
	'overview',
	'pages',
	'images',
	'cache',
	'queries',
	'recommendations',
];

function statusClass( status: WebVitalStatus ): string {
	return `performance-dashboard__status performance-dashboard__status--${ status }`;
}

export function PerformanceDashboard( props: PerformanceDashboardProps ): JSX.Element {
	const {
		initialRange = '7d',
		initialTab = 'overview',
		tabs = DEFAULT_TABS,
		className,
		cardClassName,
		...hookOptions
	} = props;

	const { loadDashboard } = usePerformance( hookOptions );
	const [ range, setRange ] = useState<DateRangeKey>( initialRange );
	const [ tab, setTab ] = useState<PerformanceDashboardTab>( tabs.includes( initialTab ) ? initialTab : tabs[ 0 ] );

	const { data, loading, error, reload } = useAsyncPayload<DashboardPayload, [ DateRangeKey ]>(
		() => loadDashboard( range ),
		[ range ],
	);

	const navigate = useCallback( ( target: string ): void => {
		if ( ( tabs as string[] ).includes( target ) ) {
			setTab( target as PerformanceDashboardTab );
		}
	}, [ tabs ] );

	const containerClass = [ 'performance-dashboard', className ].filter( Boolean ).join( ' ' );
	const cardClass = [ 'performance-dashboard__card', cardClassName ].filter( Boolean ).join( ' ' );

	return (
		<div className={ containerClass } data-testid="performance-dashboard">
			<div className="performance-dashboard__toolbar">
				<div role="group" aria-label="Date range">
					{ RANGES.map( ( key ) => (
						<button key={ key } type="button" aria-pressed={ range === key } onClick={ () => setRange( key ) }>
							{ key }
						</button>
					) ) }
				</div>
				<button type="button" onClick={ () => void reload() }>Refresh</button>
			</div>

			<div className="performance-dashboard__tabs" role="tablist">
				{ tabs.map( ( key ) => (
					<button
						key={ key }
						role="tab"
						type="button"
						aria-selected={ tab === key }
						onClick={ () => setTab( key ) }
					>
						{ key }
					</button>
				) ) }
			</div>

			<div className="performance-dashboard__panel" role="tabpanel">
				{ error && <p role="alert">{ error.message }</p> }
				{ loading && <p>Loading…</p> }

				{ data && 'overview' === tab && (
					<section className={ cardClass }>
						<h3>Core Web Vitals</h3>
						{ 0 === data.overview.length ? (
							<p>No metrics recorded for this range yet.</p>
						) : (
							<table>
								<thead>
									<tr>
										<th>Metric</th>
										<th>p75</th>
										<th>Samples</th>
										<th>Status</th>
									</tr>
								</thead>
								<tbody>
									{ data.overview.map( ( row ) => (
										<tr key={ row.metric }>
											<th scope="row">{ row.metric }</th>
											<td>{ null === row.p75 ? '—' : row.p75.toFixed( 2 ) }</td>
											<td>{ row.sample_count }</td>
											<td><span className={ statusClass( row.status ) }>{ row.status }</span></td>
										</tr>
									) ) }
								</tbody>
							</table>
						) }
					</section>
				) }

				{ data && 'pages' === tab && (
					<section className={ cardClass }>
						<h3>Slowest pages</h3>
						{ 0 === data.pages.length ? (
							<p>No page metrics recorded for this range yet.</p>
						) : (
							<table>
								<thead>
									<tr>
										<th>Route</th>
										<th>Metric</th>
										<th>p75</th>
										<th>Samples</th>
									</tr>
								</thead>
								<tbody>
									{ data.pages.map( ( row, i ) => (
										<tr key={ `${ row.route }-${ row.metric }-${ i }` }>
											<td>{ row.route ?? '—' }</td>
											<td>{ row.metric }</td>
											<td>{ row.p75.toFixed( 2 ) }</td>
											<td>{ row.sample_count }</td>
										</tr>
									) ) }
								</tbody>
							</table>
						) }
					</section>
				) }

				{ 'images' === tab && (
					<section className={ cardClass }>
						<h3>Images</h3>
						<p>Use `LazyImage` and `ResponsiveImage` in the host app to serve optimized image variants.</p>
					</section>
				) }

				{ 'cache' === tab && (
					<section className={ cardClass }>
						<MetricsChart metrics={ [ 'LCP' ] } { ...hookOptions } />
						<CacheManager { ...hookOptions } />
					</section>
				) }

				{ 'queries' === tab && (
					<section className={ cardClass }>
						<QueryAnalyzer initialRange={ range } { ...hookOptions } />
					</section>
				) }

				{ 'recommendations' === tab && (
					<section className={ cardClass }>
						<RecommendationsPanel initialRange={ range } onNavigate={ navigate } { ...hookOptions } />
					</section>
				) }
			</div>
		</div>
	);
}
