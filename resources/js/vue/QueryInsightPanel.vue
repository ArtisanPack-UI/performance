<script setup lang="ts">
/**
 * QueryInsightPanel — Vue trigger for the PerformanceInsightAgent.
 *
 * Package-local (not exported through @artisanpack-ui/vue) because the
 * agent's input shape and rendering are specific to the performance
 * package's domain model.
 *
 * @since 1.1.0
 */

import { computed, ref } from 'vue';

import {
	getPerformanceClient,
	PerformanceAiError,
	type PerformanceClient,
	type PerformanceClientOptions,
	type QueryInsight,
	type QueryInsightInput,
} from '../performance';

interface Props {
	client?: PerformanceClient;
	clientOptions?: PerformanceClientOptions;
	initialQuery?: string;
	initialExplain?: string;
	initialSchema?: string;
	initialConnection?: string;
	initialTimeMs?: number | null;
}

const props = withDefaults( defineProps<Props>(), {
	initialQuery: '',
	initialExplain: '',
	initialSchema: '',
	initialConnection: '',
	initialTimeMs: null,
} );

const emit = defineEmits<{
	( event: 'insight', insight: QueryInsight ): void;
}>();

const resolvedClient: PerformanceClient = props.client ?? getPerformanceClient( props.clientOptions );

const query = ref<string>( props.initialQuery );
const explain = ref<string>( props.initialExplain );
const schema = ref<string>( props.initialSchema );
const connection = ref<string>( props.initialConnection );
const timeMs = ref<number | null>( props.initialTimeMs );
const insight = ref<QueryInsight | null>( null );
const error = ref<string | null>( null );
const isLoading = ref<boolean>( false );

const disabled = computed<boolean>( () => isLoading.value || '' === query.value.trim() );

function parseSchema( raw: string ): QueryInsightInput['schema'] {
	const trimmed = raw.trim();
	if ( '' === trimmed ) {
		return null;
	}
	try {
		const parsed = JSON.parse( trimmed );
		if ( 'object' === typeof parsed && null !== parsed ) {
			return parsed as QueryInsightInput['schema'];
		}
	} catch {
		// Not JSON — pass the raw text through so the agent can still use
		// it as a plain-text schema hint.
	}
	return trimmed;
}

async function analyze(): Promise<void> {
	error.value = null;
	insight.value = null;
	isLoading.value = true;

	try {
		const parsedSchema = parseSchema( schema.value );
		const explainValue = '' === explain.value.trim() ? null : explain.value;
		const connectionValue = '' === connection.value.trim() ? null : connection.value.trim();

		const result = await resolvedClient.suggestQueryInsight( {
			query: query.value,
			explain: explainValue,
			schema: parsedSchema,
			time_ms: timeMs.value,
			connection: connectionValue,
		} );

		insight.value = result;
		emit( 'insight', result );
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
	<div class="performance-ai-panel" data-feature="performance.query_insight">
		<div class="performance-ai-panel__fields">
			<label class="performance-ai-panel__label" for="query-insight-query-vue">Query</label>
			<textarea
				id="query-insight-query-vue"
				v-model="query"
				class="performance-ai-panel__textarea"
				rows="5"
				placeholder="SELECT * FROM …"
			/>

			<label class="performance-ai-panel__label" for="query-insight-explain-vue">EXPLAIN plan (optional)</label>
			<textarea
				id="query-insight-explain-vue"
				v-model="explain"
				class="performance-ai-panel__textarea"
				rows="4"
				placeholder="EXPLAIN output …"
			/>

			<label class="performance-ai-panel__label" for="query-insight-schema-vue">
				Relevant schema (optional, JSON or text)
			</label>
			<textarea
				id="query-insight-schema-vue"
				v-model="schema"
				class="performance-ai-panel__textarea"
				rows="4"
				placeholder='{ "articles": { "id": "bigint", "slug": "varchar(191)" } }'
			/>

			<div class="performance-ai-panel__meta">
				<label class="performance-ai-panel__label" for="query-insight-connection-vue">Connection</label>
				<input
					id="query-insight-connection-vue"
					v-model="connection"
					class="performance-ai-panel__input"
					type="text"
					placeholder="mysql"
				/>

				<label class="performance-ai-panel__label" for="query-insight-time-vue">Observed duration (ms)</label>
				<input
					id="query-insight-time-vue"
					v-model.number="timeMs"
					class="performance-ai-panel__input"
					type="number"
					step="0.01"
					placeholder="120.5"
				/>
			</div>
		</div>

		<button
			type="button"
			class="performance-ai-panel__button"
			:disabled="disabled"
			@click="analyze"
		>
			<template v-if="isLoading">Analyzing…</template>
			<template v-else>Analyze query</template>
		</button>

		<p v-if="error" class="performance-ai-panel__error" role="alert">{{ error }}</p>

		<div v-if="insight" class="performance-ai-panel__result">
			<p v-if="insight.summary" class="performance-ai-panel__summary">{{ insight.summary }}</p>

			<template v-if="insight.bottlenecks.length > 0">
				<h4 class="performance-ai-panel__heading">Bottlenecks</h4>
				<ol class="performance-ai-panel__list">
					<li v-for="( bottleneck, index ) in insight.bottlenecks" :key="`bottleneck-${ index }`">
						{{ bottleneck }}
					</li>
				</ol>
			</template>

			<template v-if="insight.suggested_indexes.length > 0">
				<h4 class="performance-ai-panel__heading">Suggested indexes</h4>
				<ul class="performance-ai-panel__list">
					<li v-for="( index, key ) in insight.suggested_indexes" :key="`index-${ key }`">
						<code>{{ index.table }}({{ index.columns.join( ', ' ) }})</code>
						<span v-if="index.rationale" class="performance-ai-panel__rationale">{{ index.rationale }}</span>
					</li>
				</ul>
			</template>

			<template v-if="insight.rewrites.length > 0">
				<h4 class="performance-ai-panel__heading">Suggested rewrites</h4>
				<ul class="performance-ai-panel__list">
					<li v-for="( rewrite, index ) in insight.rewrites" :key="`rewrite-${ index }`">
						<div class="performance-ai-panel__rewrite">
							<div class="performance-ai-panel__rewrite-before">
								<span class="performance-ai-panel__rewrite-label">Before</span>
								<code>{{ rewrite.original }}</code>
							</div>
							<div class="performance-ai-panel__rewrite-after">
								<span class="performance-ai-panel__rewrite-label">After</span>
								<code>{{ rewrite.suggested }}</code>
							</div>
						</div>
						<span v-if="rewrite.rationale" class="performance-ai-panel__rationale">
							{{ rewrite.rationale }}
						</span>
					</li>
				</ul>
			</template>

			<template v-if="insight.caveats.length > 0">
				<h4 class="performance-ai-panel__heading">Caveats</h4>
				<ul class="performance-ai-panel__list performance-ai-panel__list--muted">
					<li v-for="( caveat, index ) in insight.caveats" :key="`caveat-${ index }`">
						{{ caveat }}
					</li>
				</ul>
			</template>
		</div>
	</div>
</template>
