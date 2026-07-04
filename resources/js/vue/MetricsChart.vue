<script setup lang="ts">
/**
 * MetricsChart — Vue port of the `MetricsChart` Livewire component.
 *
 * @since 1.0.0
 */

import { computed, nextTick, ref, watch } from 'vue';
import { renderPerfChart } from '../metrics-chart.js';
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

// Sync local `range` when the parent updates `initialRange` (dashboard
// range picker); without this the ref is a one-shot snapshot.
watch( () => props.initialRange, ( next ) => {
	range.value = next;
} );

const metricsKey = computed( () => props.metrics.join( ',' ) );

const { data, loading, error } = useAsyncPayload(
	() => loadChart( {
		metrics: props.metrics,
		range: range.value,
		showThreshold: props.showThreshold,
		type: props.initialType,
	} ),
	[ range, metricsKey, () => props.showThreshold, () => props.initialType ],
);

const payloadJson = computed( () => ( data.value ? JSON.stringify( data.value ) : '' ) );
const containerClass = computed( () => [ 'performance-metrics-chart', props.className ].filter( Boolean ).join( ' ' ) );
const canvasContainer = ref<HTMLDivElement | null>( null );

// Trigger the Chart.js bootstrap on every payload update. The auto-boot
// in metrics-chart.js fires on DOMContentLoaded (before Vue mounts) and
// on Livewire morph events; framework-only hosts need this hook.
watch( payloadJson, ( next ) => {
	if ( '' === next ) {
		return;
	}
	void nextTick( () => {
		if ( null !== canvasContainer.value ) {
			renderPerfChart( canvasContainer.value );
		}
	} );
}, { immediate: true } );
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
			<div ref="canvasContainer" class="performance-metrics-chart__canvas" data-metrics-chart :data-chart-payload="payloadJson">
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
