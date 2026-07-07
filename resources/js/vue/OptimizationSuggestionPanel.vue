<script setup lang="ts">
/**
 * OptimizationSuggestionPanel — Vue trigger for the OptimizationSuggestionAgent.
 *
 * @since 1.1.0
 */

import { computed, ref } from 'vue';

import {
	getPerformanceClient,
	type OptimizationMetricRow,
	type OptimizationSuggestion,
	PerformanceAiError,
	type PerformanceClient,
	type PerformanceClientOptions,
} from '../performance';

interface Props {
	client?: PerformanceClient;
	clientOptions?: PerformanceClientOptions;
	metrics: OptimizationMetricRow[];
	range: { from: string; to: string };
	initialBusinessPriority?: string;
	initialRecentChanges?: string;
	initialTrafficMix?: string;
}

const props = withDefaults( defineProps<Props>(), {
	initialBusinessPriority: '',
	initialRecentChanges: '',
	initialTrafficMix: '',
} );

const emit = defineEmits<{
	( event: 'suggestion', suggestion: OptimizationSuggestion ): void;
}>();

const resolvedClient: PerformanceClient = props.client ?? getPerformanceClient( props.clientOptions );

const businessPriority = ref<string>( props.initialBusinessPriority );
const recentChanges = ref<string>( props.initialRecentChanges );
const trafficMix = ref<string>( props.initialTrafficMix );
const suggestion = ref<OptimizationSuggestion | null>( null );
const error = ref<string | null>( null );
const isLoading = ref<boolean>( false );

const disabled = computed<boolean>( () => isLoading.value || 0 === props.metrics.length );

async function suggest(): Promise<void> {
	error.value = null;
	suggestion.value = null;
	isLoading.value = true;

	try {
		const context: Record<string, string> = {};
		if ( '' !== businessPriority.value.trim() ) {
			context.business_priority = businessPriority.value.trim();
		}
		if ( '' !== recentChanges.value.trim() ) {
			context.recent_changes = recentChanges.value.trim();
		}
		if ( '' !== trafficMix.value.trim() ) {
			context.traffic_mix = trafficMix.value.trim();
		}

		const result = await resolvedClient.suggestOptimization( {
			range: props.range,
			metrics: props.metrics,
			context: 0 === Object.keys( context ).length ? undefined : context,
		} );

		suggestion.value = result;
		emit( 'suggestion', result );
	} catch ( caught ) {
		if ( caught instanceof PerformanceAiError ) {
			error.value = caught.message;
		} else if ( caught instanceof Error ) {
			error.value = caught.message;
		} else {
			error.value = 'The AI agent could not complete this request.';
		}
	} finally {
		isLoading.value = false;
	}
}
</script>

<template>
	<div class="performance-ai-panel" data-feature="performance.optimization_suggestion">
		<div class="performance-ai-panel__fields">
			<label class="performance-ai-panel__label" for="optimization-priority-vue">
				Business priority (optional)
			</label>
			<input
				id="optimization-priority-vue"
				v-model="businessPriority"
				class="performance-ai-panel__input"
				type="text"
				placeholder="checkout > blog"
			/>

			<label class="performance-ai-panel__label" for="optimization-recent-vue">
				Recent changes (optional)
			</label>
			<textarea
				id="optimization-recent-vue"
				v-model="recentChanges"
				class="performance-ai-panel__textarea"
				rows="2"
				placeholder="switched to Vite last week"
			/>

			<label class="performance-ai-panel__label" for="optimization-traffic-vue">
				Traffic mix (optional)
			</label>
			<input
				id="optimization-traffic-vue"
				v-model="trafficMix"
				class="performance-ai-panel__input"
				type="text"
				placeholder="65% mobile, 35% desktop"
			/>
		</div>

		<button
			type="button"
			class="performance-ai-panel__button"
			:disabled="disabled"
			@click="suggest"
		>
			<template v-if="isLoading">Analyzing…</template>
			<template v-else>Suggest optimizations</template>
		</button>

		<p v-if="error" class="performance-ai-panel__error" role="alert">{{ error }}</p>

		<div v-if="suggestion" class="performance-ai-panel__result">
			<p v-if="suggestion.summary" class="performance-ai-panel__summary">{{ suggestion.summary }}</p>

			<template v-if="suggestion.focus_areas.length > 0">
				<h4 class="performance-ai-panel__heading">Focus areas</h4>
				<ul class="performance-ai-panel__list">
					<li
						v-for="( area, index ) in suggestion.focus_areas"
						:key="`focus-${ index }`"
						class="performance-ai-panel__focus-area"
					>
						<div class="performance-ai-panel__focus-area-header">
							<strong>{{ area.title }}</strong>
							<span :class="`performance-ai-panel__tag performance-ai-panel__tag--impact-${ area.impact }`">
								Impact: {{ area.impact }}
							</span>
							<span :class="`performance-ai-panel__tag performance-ai-panel__tag--effort-${ area.effort }`">
								Effort: {{ area.effort }}
							</span>
						</div>

						<p v-if="area.routes.length > 0" class="performance-ai-panel__routes">
							Routes:
							<template v-for="( route, routeIndex ) in area.routes" :key="`route-${ index }-${ routeIndex }`">
								<code>{{ route }}</code><template v-if="routeIndex < area.routes.length - 1">, </template>
							</template>
						</p>

						<p v-if="area.rationale" class="performance-ai-panel__rationale">{{ area.rationale }}</p>

						<ul v-if="area.actions.length > 0" class="performance-ai-panel__actions">
							<li
								v-for="( action, actionIndex ) in area.actions"
								:key="`action-${ index }-${ actionIndex }`"
							>
								{{ action }}
							</li>
						</ul>
					</li>
				</ul>
			</template>

			<template v-if="suggestion.quick_wins.length > 0">
				<h4 class="performance-ai-panel__heading">Quick wins</h4>
				<ul class="performance-ai-panel__list">
					<li v-for="( win, index ) in suggestion.quick_wins" :key="`win-${ index }`">
						{{ win }}
					</li>
				</ul>
			</template>

			<template v-if="suggestion.caveats.length > 0">
				<h4 class="performance-ai-panel__heading">Caveats</h4>
				<ul class="performance-ai-panel__list performance-ai-panel__list--muted">
					<li v-for="( caveat, index ) in suggestion.caveats" :key="`caveat-${ index }`">
						{{ caveat }}
					</li>
				</ul>
			</template>
		</div>
	</div>
</template>
