<script setup lang="ts">
/**
 * PerfPrefetch — Vue port of the Blade `<x-perf-prefetch>` component.
 *
 * @since 1.0.0
 */

import { onBeforeUnmount, onMounted, watch } from 'vue';

interface PerfPrefetchProps {
	urls: string | string[];
	as?: 'document' | 'script' | 'style' | 'image' | 'font' | 'fetch';
}

const props = defineProps<PerfPrefetchProps>();

let elements: HTMLLinkElement[] = [];

function normalize( input: string | string[] ): string[] {
	const list = Array.isArray( input ) ? input : [ input ];
	const seen = new Set<string>();
	list.forEach( ( raw ) => {
		if ( 'string' !== typeof raw ) {
			return;
		}
		const url = raw.trim();
		if ( '' !== url ) {
			seen.add( url );
		}
	} );
	return Array.from( seen );
}

function install(): void {
	uninstall();
	if ( typeof document === 'undefined' ) {
		return;
	}
	elements = normalize( props.urls ).map( ( href ) => {
		const link = document.createElement( 'link' );
		link.rel = 'prefetch';
		link.href = href;
		if ( props.as ) {
			link.setAttribute( 'as', props.as );
		}
		document.head.appendChild( link );
		return link;
	} );
}

function uninstall(): void {
	elements.forEach( ( link ) => link.remove() );
	elements = [];
}

onMounted( install );
watch(
	() => [ props.urls, props.as ],
	() => install(),
	{ deep: true },
);
onBeforeUnmount( uninstall );
</script>

<template>
	<!-- Renders nothing directly; <link> tags are injected into <head>. -->
</template>
