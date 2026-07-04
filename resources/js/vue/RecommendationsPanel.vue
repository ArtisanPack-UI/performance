<script setup lang="ts">
/**
 * RecommendationsPanel — Vue port of the Livewire component.
 *
 * @since 1.0.0
 */

import { computed, ref } from 'vue';
import type { DateRangeKey } from '../performance';
import { useAsyncPayload, usePerformance, type UsePerformanceOptions } from './usePerformance';

interface RecommendationsPanelProps extends UsePerformanceOptions {
	initialRange?: DateRangeKey;
	className?: string;
}

const props = withDefaults( defineProps<RecommendationsPanelProps>(), {
	initialRange: '7d',
} );

const emit = defineEmits<{
	( e: 'navigate', tab: string ): void;
	( e: 'generate-index-migration', payload: { table: string; columns: string[] } ): void;
}>();

const { loadRecommendations, runRecommendationAction } = usePerformance( props );
const range = ref<DateRangeKey>( props.initialRange );
const status = ref<{ message: string; isError: boolean } | null>( null );
const working = ref( false );

const { data, loading, error, reload } = useAsyncPayload(
	() => loadRecommendations( range.value ),
	[ range ],
);

async function runAction( action: 'apply' | 'dismiss' | 'reset', id?: string ): Promise<void> {
	working.value = true;
	try {
		const result = await runRecommendationAction( action, id ? { id } : {} );
		status.value = { message: result.message, isError: result.is_error };
		if ( 'apply' === action && id ) {
			const rec = data.value?.items.find( ( item ) => item.id === id );
			if ( rec ) {
				if ( 'generate-index-migration' === rec.action ) {
					const p = rec.action_payload ?? {};
					emit( 'generate-index-migration', {
						table: String( p.table ?? '' ),
						columns: Array.isArray( p.columns ) ? ( p.columns as string[] ) : [],
					} );
				} else if ( 'view-query-analyzer' === rec.action ) {
					emit( 'navigate', 'queries' );
				}
			}
		}
		await reload();
	} catch ( err ) {
		status.value = { message: err instanceof Error ? err.message : String( err ), isError: true };
	} finally {
		working.value = false;
	}
}

const containerClass = computed( () => [ 'performance-recommendations', props.className ].filter( Boolean ).join( ' ' ) );
</script>

<template>
	<div :class="containerClass" data-testid="recommendations-panel">
		<div v-if="status" :role="status.isError ? 'alert' : 'status'" :class="{ 'is-error': status.isError }">
			{{ status.message }}
		</div>
		<p v-if="error" role="alert">{{ error.message }}</p>
		<p v-if="loading">Loading…</p>
		<template v-if="data">
			<button
				v-if="data.dismissed.length > 0"
				type="button"
				:disabled="working"
				@click="runAction( 'reset' )"
			>
				Restore dismissed ({{ data.dismissed.length }})
			</button>
			<ul>
				<li v-for="item in data.items" :key="item.id" :class="`priority-${ item.priority }`">
					<h4>
						<span :class="`priority-badge priority-badge--${ item.priority }`">{{ item.priority }}</span>
						{{ item.title }}
					</h4>
					<p v-if="item.description">{{ item.description }}</p>
					<details v-if="item.manual_steps && item.manual_steps.length > 0">
						<summary>Manual steps</summary>
						<ol>
							<li v-for="( step, i ) in item.manual_steps" :key="i">{{ step }}</li>
						</ol>
					</details>
					<div>
						<button
							v-if="item.action"
							type="button"
							:disabled="working"
							@click="runAction( 'apply', item.id )"
						>
							Apply fix
						</button>
						<button type="button" :disabled="working" @click="runAction( 'dismiss', item.id )">
							Dismiss
						</button>
					</div>
				</li>
			</ul>
		</template>
	</div>
</template>
