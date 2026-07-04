<script setup lang="ts">
/**
 * MetricsChart — Vue port of the `MetricsChart` Livewire component.
 *
 * @since 1.0.0
 */

import { computed, ref } from 'vue';
import type { ChartRangeKey } from '../performance';
import { useAsyncPayload, usePerformance, type UsePerformanceOptions } from './usePerformance';

interface MetricsChartProps extends UsePerformanceOptions {
	metrics?: string[];
	initialRange?: ChartRangeKey;
	initialType?: 'line' | 'bar' | 'area';
	showThreshold?: boolean;
	className?: string;
}

const props = withDefaults( defineProps<MetricsChartProps>(), {
	metrics: () => [ 'LCP' ],
	initialRange: '7d',
	initialType: 'line',
	showThreshold: true,
} );

const RANGES: ChartRangeKey[] = [ '7d', '30d', '90d' ];
const { loadChart } = usePerformance( props );
const range = ref<ChartRangeKey>( props.initialRange );

const { data, loading, error } = useAsyncPayload(
	() => loadChart( {
		metrics: props.metrics,
		range: range.value,
		showThreshold: props.showThreshold,
		type: props.initialType,
	} ),
	[ range ],
);

const payloadJson = computed( () => ( data.value ? JSON.stringify( data.value ) : '' ) );
const containerClass = computed( () => [ 'performance-metrics-chart', props.className ].filter( Boolean ).join( ' ' ) );
</script>

<template>
	<div :class="containerClass" data-testid="performance-metrics-chart">
		<div class="performance-metrics-chart__range" role="group" aria-label="Date range">
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
		<p v-if="error" role="alert">{{ error.message }}</p>
		<p v-if="loading">Loading…</p>
		<template v-if="data">
			<div class="performance-metrics-chart__canvas" data-metrics-chart :data-chart-payload="payloadJson">
				<canvas />
			</div>
			<details>
				<summary>Data</summary>
				<table>
					<thead>
						<tr>
							<th>Date</th>
							<th v-for="ds in data.datasets" :key="ds.metric">{{ ds.metric }}</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="( label, i ) in data.labels" :key="label">
							<th scope="row">{{ label }}</th>
							<td v-for="ds in data.datasets" :key="ds.metric">
								{{ null === ds.values[ i ] ? '—' : ds.values[ i ] }}
							</td>
						</tr>
					</tbody>
				</table>
			</details>
		</template>
	</div>
</template>
