<script setup lang="ts">
/**
 * SpeculativeRules — Vue port of the Blade `<x-perf-speculative-rules>`.
 *
 * Injects a `<script type="speculationrules">` into `<head>`.
 *
 * @since 1.0.0
 */

import { onBeforeUnmount, onMounted, watch } from 'vue';

interface SpeculationRules {
	prefetch?: unknown;
	prerender?: unknown;
}

interface SpeculativeRulesProps {
	rules: SpeculationRules | string;
}

const props = defineProps<SpeculativeRulesProps>();

let element: HTMLScriptElement | null = null;

function supportsSpeculationRules(): boolean {
	if ( typeof window === 'undefined' || typeof HTMLScriptElement === 'undefined' ) {
		return false;
	}
	const supports = ( HTMLScriptElement as unknown as { supports?: ( key: string ) => boolean } ).supports;
	return 'function' === typeof supports ? supports.call( HTMLScriptElement, 'speculationrules' ) : false;
}

function install(): void {
	uninstall();
	if ( ! supportsSpeculationRules() ) {
		return;
	}
	const json = 'string' === typeof props.rules ? props.rules : JSON.stringify( props.rules );
	if ( '' === json || '{}' === json ) {
		return;
	}
	element = document.createElement( 'script' );
	element.type = 'speculationrules';
	element.textContent = json;
	document.head.appendChild( element );
}

function uninstall(): void {
	if ( null !== element ) {
		element.remove();
		element = null;
	}
}

onMounted( install );
watch( () => props.rules, install );
onBeforeUnmount( uninstall );
</script>

<template>
	<!-- Renders nothing directly; the script tag is injected into <head>. -->
</template>
