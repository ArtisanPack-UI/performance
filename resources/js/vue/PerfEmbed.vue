<script setup lang="ts">
/**
 * PerfEmbed — Vue port of the Blade `<x-perf-embed>` component.
 *
 * @since 1.0.0
 */

import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

export type PerfEmbedProvider = 'youtube' | 'vimeo' | 'twitter' | 'x';
export type PerfEmbedMode = 'iframe' | 'blockquote';

export interface PerfEmbedFacade {
	provider: PerfEmbedProvider | string;
	id: string;
	mode: PerfEmbedMode;
	title: string;
	iframe_url: string;
	embed_html: string;
	widgets_script: string;
	thumbnail: string;
}

interface PerfEmbedProps {
	facade: PerfEmbedFacade | null;
	lazy?: boolean;
	showFacade?: boolean;
	className?: string;
	width?: number;
	height?: number;
	error?: string;
}

const props = withDefaults( defineProps<PerfEmbedProps>(), {
	lazy: true,
	showFacade: true,
} );

const emit = defineEmits<{
	( e: 'activate', facade: PerfEmbedFacade ): void;
}>();

const activated = ref( false );

const containerClass = computed( () => [ 'perf-embed', props.className ].filter( Boolean ).join( ' ' ) );

const style = computed( () =>
	undefined !== props.width && undefined !== props.height
		? { aspectRatio: `${ props.width } / ${ props.height }` }
		: undefined,
);

const showEager = computed( () => ! props.lazy || activated.value );

function activate(): void {
	if ( ! props.facade ) {
		return;
	}
	activated.value = true;
	emit( 'activate', props.facade );
}

function onFacadeKey( event: KeyboardEvent ): void {
	if ( 'Enter' === event.key || ' ' === event.key ) {
		activate();
	}
}

// Widget scripts (Twitter/X blockquote embeds) can't sit inline in the
// template — Vue's SFC compiler strips literal <script> tags from
// templates as side-effect elements. Inject it programmatically into
// the blockquote wrapper once the eager mode swaps in, and remove it on
// unmount so route changes don't stack duplicate scripts.
// Plain template ref (works on Vue 3.4+; useTemplateRef requires 3.5+).
const blockquoteRoot = ref<HTMLDivElement | null>( null );
let widgetsScript: HTMLScriptElement | null = null;

function removeWidgetsScript(): void {
	if ( null !== widgetsScript ) {
		widgetsScript.remove();
		widgetsScript = null;
	}
}

function installWidgetsScript(): void {
	removeWidgetsScript();
	if ( ! props.facade || 'blockquote' !== props.facade.mode || ! showEager.value ) {
		return;
	}
	const src = props.facade.widgets_script;
	if ( '' === src ) {
		return;
	}
	const root = blockquoteRoot.value;
	if ( null === root ) {
		return;
	}
	const script = document.createElement( 'script' );
	script.async = true;
	script.src = src;
	script.charset = 'utf-8';
	root.appendChild( script );
	widgetsScript = script;
}

watch(
	[ () => props.facade, showEager ],
	() => {
		void nextTick( installWidgetsScript );
	},
	{ immediate: true },
);

onBeforeUnmount( removeWidgetsScript );
</script>

<template>
	<template v-if="props.facade">
		<template v-if="showEager">
			<div v-if="'blockquote' === props.facade.mode" ref="blockquoteRoot" :class="containerClass" :style="style">
				<div v-html="props.facade.embed_html" />
			</div>
			<iframe
				v-else
				:src="props.facade.iframe_url"
				:title="props.facade.title"
				loading="eager"
				:width="props.width"
				:height="props.height"
				allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
				referrerpolicy="strict-origin-when-cross-origin"
				allowfullscreen
				:class="containerClass"
			/>
		</template>
		<div
			v-else-if="! props.showFacade"
			:class="`${ containerClass } perf-embed-facade`"
			:data-provider="props.facade.provider"
			:data-id="props.facade.id"
			:data-mode="props.facade.mode"
			:data-title="props.facade.title"
			:data-iframe-url="props.facade.iframe_url"
			:data-widgets-script="props.facade.widgets_script"
			:style="style"
			role="button"
			tabindex="0"
			:aria-label="props.facade.title"
			@click="activate"
			@keydown="onFacadeKey"
		/>
		<div
			v-else
			:class="`${ containerClass } perf-embed-facade`"
			:data-provider="props.facade.provider"
			:data-id="props.facade.id"
			:data-mode="props.facade.mode"
			:data-title="props.facade.title"
			:data-iframe-url="props.facade.iframe_url"
			:data-widgets-script="props.facade.widgets_script"
			:style="style"
			@click="activate"
		>
			<img
				v-if="'' !== props.facade.thumbnail"
				:src="props.facade.thumbnail"
				:alt="props.facade.title"
				loading="lazy"
				decoding="async"
				class="perf-embed-thumbnail"
			/>
			<div
				v-else
				class="perf-embed-thumbnail perf-embed-thumbnail--placeholder"
				aria-hidden="true"
			/>
			<button
				type="button"
				class="perf-embed-play"
				:aria-label="props.facade.title"
				@click.stop="activate"
			>
				<svg viewBox="0 0 24 24" width="48" height="48" aria-hidden="true" focusable="false">
					<path d="M8 5v14l11-7z" fill="currentColor" />
				</svg>
			</button>
		</div>
	</template>
</template>
