<script setup lang="ts">
/**
 * CacheManager — Vue port of the `CacheManager` Livewire component.
 *
 * @since 1.0.0
 */

import { computed, onMounted, ref } from 'vue';
import type { CachePayload } from '../performance';
import { usePerformance, type UsePerformanceOptions } from './usePerformance';

interface CacheManagerProps extends UsePerformanceOptions {
	className?: string;
}

const props = defineProps<CacheManagerProps>();

const { loadCache, runCacheAction } = usePerformance( props );
const payload = ref<CachePayload | null>( null );
const loading = ref( true );
const working = ref( false );
const keyInput = ref( '' );
const status = ref<{ message: string; isError: boolean } | null>( null );

type PendingAction =
	| null
	| 'flush'
	| { kind: 'entry'; index: number }
	| { kind: 'tag'; index: number }
	| { kind: 'key'; value: string };

const pending = ref<PendingAction>( null );

async function refresh(): Promise<void> {
	loading.value = true;
	try {
		payload.value = await loadCache();
	} finally {
		loading.value = false;
	}
}

async function runAction(
	action: 'flush' | 'warm' | 'invalidate-key' | 'invalidate-tag',
	body: { key?: string; tag?: string } = {},
): Promise<void> {
	working.value = true;
	try {
		const result = await runCacheAction( action, body );
		status.value = { message: result.message, isError: result.is_error };
		if ( result.summary && result.page_entries && result.fragment_tags ) {
			payload.value = {
				summary: result.summary,
				page_entries: result.page_entries,
				fragment_tags: result.fragment_tags,
			};
		}
	} catch ( err ) {
		status.value = { message: err instanceof Error ? err.message : String( err ), isError: true };
	} finally {
		working.value = false;
		pending.value = null;
	}
}

function requestKey(): void {
	const value = keyInput.value.trim();
	if ( '' !== value ) {
		pending.value = { kind: 'key', value };
	}
}

const containerClass = computed( () => [ 'performance-cache-manager', props.className ].filter( Boolean ).join( ' ' ) );

function isEntryPending( index: number ): boolean {
	return null !== pending.value && 'object' === typeof pending.value && 'entry' === pending.value.kind && pending.value.index === index;
}

function isTagPending( index: number ): boolean {
	return null !== pending.value && 'object' === typeof pending.value && 'tag' === pending.value.kind && pending.value.index === index;
}

const isKeyPending = computed( () =>
	null !== pending.value
	&& 'object' === typeof pending.value
	&& 'key' === pending.value.kind
	&& pending.value.value === keyInput.value.trim(),
);

onMounted( () => void refresh() );
</script>

<template>
	<div :class="containerClass" data-testid="performance-cache-manager">
		<template v-if="loading || null === payload">
			Loading…
		</template>
		<template v-else>
			<div
				v-if="status"
				:role="status.isError ? 'alert' : 'status'"
				:class="{ 'performance-cache-manager__status': true, 'is-error': status.isError }"
				data-testid="status-banner"
			>
				{{ status.message }}
			</div>

			<section>
				<h3>Page Cache</h3>
				<dl>
					<dt>Entries</dt><dd>{{ payload.summary.page.entries }}</dd>
					<dt>Size</dt><dd>{{ payload.summary.page.size_bytes ?? 'N/A' }}</dd>
					<dt>Hit rate</dt><dd>{{ payload.summary.page.hit_rate ?? 'N/A' }}</dd>
				</dl>
				<form @submit.prevent="requestKey">
					<label>Invalidate key or pattern
						<input v-model="keyInput" />
					</label>
					<button type="submit" :disabled="working">Request invalidation</button>
				</form>
				<div v-if="isKeyPending">
					<p>Invalidate <code>{{ keyInput }}</code>?</p>
					<button type="button" :disabled="working" @click="runAction( 'invalidate-key', { key: keyInput.trim() } )">Confirm</button>
					<button type="button" @click="pending = null">Cancel</button>
				</div>
				<table>
					<thead>
						<tr><th>Path</th><th /></tr>
					</thead>
					<tbody>
						<tr v-for="( entry, index ) in payload.page_entries" :key="entry.key">
							<td>{{ entry.path }}</td>
							<td>
								<template v-if="isEntryPending( index )">
									<button type="button" :disabled="working" @click="runAction( 'invalidate-key', { key: entry.path } )">Confirm</button>
									<button type="button" @click="pending = null">Cancel</button>
								</template>
								<button v-else type="button" @click="pending = { kind: 'entry', index }">Invalidate</button>
							</td>
						</tr>
					</tbody>
				</table>
			</section>

			<section>
				<h3>Fragment Cache</h3>
				<dl>
					<dt>Entries</dt><dd>{{ payload.summary.fragment.entries }}</dd>
					<dt>Tags</dt><dd>{{ payload.summary.fragment.tags }}</dd>
					<dt>Hit rate</dt><dd>{{ payload.summary.fragment.hit_rate ?? 'N/A' }}</dd>
				</dl>
				<table>
					<thead>
						<tr><th>Tag</th><th>Entries</th><th /></tr>
					</thead>
					<tbody>
						<tr v-for="( row, index ) in payload.fragment_tags" :key="row.tag">
							<td>{{ row.tag }}</td>
							<td>{{ row.entry_count }}</td>
							<td>
								<template v-if="isTagPending( index )">
									<button type="button" :disabled="working" @click="runAction( 'invalidate-tag', { tag: row.tag } )">Confirm</button>
									<button type="button" @click="pending = null">Cancel</button>
								</template>
								<button v-else type="button" @click="pending = { kind: 'tag', index }">Invalidate</button>
							</td>
						</tr>
					</tbody>
				</table>
			</section>

			<section>
				<h3>Actions</h3>
				<button type="button" data-testid="warm-cache" :disabled="working" @click="runAction( 'warm' )">Warm cache</button>
				<template v-if="'flush' === pending">
					<button type="button" data-testid="confirm-flush" :disabled="working" @click="runAction( 'flush' )">Confirm flush</button>
					<button type="button" @click="pending = null">Cancel</button>
				</template>
				<button v-else type="button" data-testid="request-flush" @click="pending = 'flush'">Flush all</button>
			</section>
		</template>
	</div>
</template>
