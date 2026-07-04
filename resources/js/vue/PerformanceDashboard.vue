<script setup lang="ts">
/**
 * PerformanceDashboard — Vue port of the Livewire dashboard.
 *
 * @since 1.0.0
 */

import { computed, ref } from 'vue';
import type { DateRangeKey, WebVitalStatus } from '../performance';
import { useAsyncPayload, usePerformance, type UsePerformanceOptions } from './usePerformance';
import CacheManager from './CacheManager.vue';
import MetricsChart from './MetricsChart.vue';
import QueryAnalyzer from './QueryAnalyzer.vue';
import RecommendationsPanel from './RecommendationsPanel.vue';

export type PerformanceDashboardTab = 'overview' | 'pages' | 'images' | 'cache' | 'queries' | 'recommendations';

interface PerformanceDashboardProps extends UsePerformanceOptions {
	initialRange?: DateRangeKey;
	initialTab?: PerformanceDashboardTab;
	tabs?: PerformanceDashboardTab[];
	className?: string;
	cardClassName?: string;
}

const props = withDefaults( defineProps<PerformanceDashboardProps>(), {
	initialRange: '7d',
	initialTab: 'overview',
	// Inlined instead of referencing a top-level const because
	// `defineProps` is hoisted outside setup() and cannot see local
	// bindings — the Vue SFC compiler errors on that reference.
	tabs: () => [ 'overview', 'pages', 'images', 'cache', 'queries', 'recommendations' ],
} );

const RANGES: DateRangeKey[] = [ '24h', '7d', '30d', '90d' ];

const { loadDashboard } = usePerformance( props );
const range = ref<DateRangeKey>( props.initialRange );
const tab = ref<PerformanceDashboardTab>(
	props.tabs.includes( props.initialTab ) ? props.initialTab : props.tabs[ 0 ],
);

const { data, loading, error, reload } = useAsyncPayload( () => loadDashboard( range.value ), [ range ] );

const containerClass = computed( () => [ 'performance-dashboard', props.className ].filter( Boolean ).join( ' ' ) );
const cardClass = computed( () => [ 'performance-dashboard__card', props.cardClassName ].filter( Boolean ).join( ' ' ) );

function statusClass( status: WebVitalStatus ): string {
	return `performance-dashboard__status performance-dashboard__status--${ status }`;
}

function navigate( target: string ): void {
	if ( ( props.tabs as string[] ).includes( target ) ) {
		tab.value = target as PerformanceDashboardTab;
	}
}
</script>

<template>
	<div :class="containerClass" data-testid="performance-dashboard">
		<div class="performance-dashboard__toolbar">
			<div role="group" aria-label="Date range">
				<button
					v-for="key in RANGES"
					:key="key"
					type="button"
					:aria-pressed="range === key"
					@click="range = key"
				>
					{{ key }}
				</button>
			</div>
			<button type="button" @click="reload()">Refresh</button>
		</div>

		<div class="performance-dashboard__tabs" role="tablist">
			<button
				v-for="key in props.tabs"
				:key="key"
				role="tab"
				type="button"
				:aria-selected="tab === key"
				@click="tab = key"
			>
				{{ key }}
			</button>
		</div>

		<div class="performance-dashboard__panel" role="tabpanel">
			<p v-if="error" role="alert">{{ error.message }}</p>
			<p v-if="loading">Loading…</p>

			<section v-if="data && 'overview' === tab" :class="cardClass">
				<h3>Core Web Vitals</h3>
				<p v-if="0 === data.overview.length">No metrics recorded for this range yet.</p>
				<table v-else>
					<thead>
						<tr><th>Metric</th><th>p75</th><th>Samples</th><th>Status</th></tr>
					</thead>
					<tbody>
						<tr v-for="row in data.overview" :key="row.metric">
							<th scope="row">{{ row.metric }}</th>
							<td>{{ null === row.p75 ? '—' : row.p75.toFixed( 2 ) }}</td>
							<td>{{ row.sample_count }}</td>
							<td><span :class="statusClass( row.status )">{{ row.status }}</span></td>
						</tr>
					</tbody>
				</table>
			</section>

			<section v-if="data && 'pages' === tab" :class="cardClass">
				<h3>Slowest pages</h3>
				<p v-if="0 === data.pages.length">No page metrics recorded for this range yet.</p>
				<table v-else>
					<thead>
						<tr><th>Route</th><th>Metric</th><th>p75</th><th>Samples</th></tr>
					</thead>
					<tbody>
						<tr v-for="( row, i ) in data.pages" :key="`${ row.route }-${ row.metric }-${ i }`">
							<td>{{ row.route ?? '—' }}</td>
							<td>{{ row.metric }}</td>
							<td>{{ row.p75.toFixed( 2 ) }}</td>
							<td>{{ row.sample_count }}</td>
						</tr>
					</tbody>
				</table>
			</section>

			<section v-if="'images' === tab" :class="cardClass">
				<h3>Images</h3>
				<p>Use `LazyImage` and `ResponsiveImage` in the host app to serve optimized image variants.</p>
			</section>

			<section v-if="'cache' === tab" :class="cardClass">
				<MetricsChart :metrics="[ 'LCP' ]" v-bind="props" />
				<CacheManager v-bind="props" />
			</section>

			<section v-if="'queries' === tab" :class="cardClass">
				<QueryAnalyzer :initial-range="range" v-bind="props" />
			</section>

			<section v-if="'recommendations' === tab" :class="cardClass">
				<RecommendationsPanel :initial-range="range" v-bind="props" @navigate="navigate" />
			</section>
		</div>
	</div>
</template>
