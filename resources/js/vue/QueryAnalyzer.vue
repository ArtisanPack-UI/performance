<script setup lang="ts">
/**
 * QueryAnalyzer — Vue port of the `QueryAnalyzer` Livewire component.
 *
 * @since 1.0.0
 */

import { computed, ref } from 'vue';
import type { DateRangeKey, QuerySortKey } from '../performance';
import { useAsyncPayload, usePerformance, type UsePerformanceOptions } from './usePerformance';

interface QueryAnalyzerProps extends UsePerformanceOptions {
	initialRange?: DateRangeKey;
	initialRoute?: string;
	initialMinTimeMs?: number;
	initialSort?: QuerySortKey;
	className?: string;
}

const props = withDefaults( defineProps<QueryAnalyzerProps>(), {
	initialRange: '7d',
	initialRoute: '',
	initialMinTimeMs: 0,
	initialSort: 'time',
} );

const RANGES: DateRangeKey[] = [ '24h', '7d', '30d', '90d' ];

const { client, loadQueries } = usePerformance( props );
const range = ref<DateRangeKey>( props.initialRange );
const route = ref<string>( props.initialRoute );
const minTimeMs = ref<number>( props.initialMinTimeMs );
const sort = ref<QuerySortKey>( props.initialSort );
const expanded = ref<string | null>( null );

const { data, loading, error, reload } = useAsyncPayload(
	() => loadQueries( {
		range: range.value,
		route: '' === route.value ? undefined : route.value,
		min_time_ms: minTimeMs.value,
		sort: sort.value,
	} ),
	[ range, route, minTimeMs, sort ],
);

async function exportCsv(): Promise<void> {
	const url = await client.exportQueriesCsvUrl( {
		range: range.value,
		route: '' === route.value ? undefined : route.value,
		min_time_ms: minTimeMs.value,
		sort: sort.value,
	} );
	window.open( url, '_blank' );
}

const containerClass = computed( () => [ 'performance-query-analyzer', props.className ].filter( Boolean ).join( ' ' ) );

function preview( row: { hash: string; query: string } ): string {
	if ( expanded.value === row.hash || row.query.length <= 120 ) {
		return row.query;
	}
	return row.query.slice( 0, 120 ) + '…';
}
</script>

<template>
	<div :class="containerClass" data-testid="query-analyzer">
		<div class="performance-query-analyzer__toolbar">
			<label>Range
				<select v-model="range">
					<option v-for="key in RANGES" :key="key" :value="key">{{ key }}</option>
				</select>
			</label>
			<label>Route
				<select v-model="route">
					<option value="">All routes</option>
					<option v-for="r in data?.available_routes ?? []" :key="r" :value="r">{{ r }}</option>
				</select>
			</label>
			<label>Min time (ms)
				<input type="number" min="0" :value="minTimeMs" @input="minTimeMs = Math.max( 0, Number( ( $event.target as HTMLInputElement ).value ) )" />
			</label>
			<div role="group" aria-label="Sort">
				<button type="button" :aria-pressed="'time' === sort" @click="sort = 'time'">Slowest</button>
				<button type="button" :aria-pressed="'frequency' === sort" @click="sort = 'frequency'">Most frequent</button>
			</div>
			<button type="button" @click="reload()">Refresh</button>
			<button type="button" @click="exportCsv()">Export CSV</button>
		</div>

		<p v-if="error" role="alert">{{ error.message }}</p>
		<p v-if="loading">Loading…</p>

		<table v-if="data" data-testid="query-analyzer-table">
			<thead>
				<tr>
					<th>Query</th><th>Peak (ms)</th><th>Count</th>
					<th>Route</th><th>File</th><th>Suggestion</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="row in data.rows" :key="row.hash">
					<td>
						<code>{{ preview( row ) }}</code>
						<button
							v-if="row.query.length > 120"
							type="button"
							@click="expanded = expanded === row.hash ? null : row.hash"
						>
							{{ expanded === row.hash ? 'Hide' : 'Show full' }}
						</button>
					</td>
					<td>{{ row.peak_time_ms.toFixed( 1 ) }}</td>
					<td>{{ row.occurrences }}</td>
					<td>{{ row.route ?? '—' }}</td>
					<td>{{ row.file ?? '—' }}{{ row.line ? `:${ row.line }` : '' }}</td>
					<td>{{ row.suggestion ?? '' }}</td>
				</tr>
			</tbody>
		</table>
	</div>
</template>
