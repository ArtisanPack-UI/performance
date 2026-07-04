<script setup lang="ts">
/**
 * LazyImage — Vue port of the Blade `<x-perf-lazy-image>` component.
 *
 * @since 1.0.0
 */

import { computed } from 'vue';

export type LazyImagePlaceholder = 'dominant_color' | 'blur' | 'skeleton' | 'none';
export type LazyImageFetchPriority = 'high' | 'low' | 'auto';

interface LazyImageProps {
	src: string;
	alt?: string;
	width?: number;
	height?: number;
	lazy?: boolean;
	placeholder?: LazyImagePlaceholder;
	dominantColor?: string;
	blurSrc?: string;
	fetchPriority?: LazyImageFetchPriority;
	threshold?: string;
	sizes?: string;
	srcset?: string;
	className?: string;
}

const props = withDefaults( defineProps<LazyImageProps>(), {
	alt: '',
	lazy: true,
	placeholder: 'none',
} );

const PLACEHOLDERS: readonly LazyImagePlaceholder[] = [
	'dominant_color',
	'blur',
	'skeleton',
	'none',
] as const;

const SAFE_BLUR_URI = /^data:image\/(?:jpeg|png|webp|avif|gif)[;,]/i;
const HEX_COLOR = /^#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/;

function isValidHexColor( color: string ): boolean {
	if ( ! HEX_COLOR.test( color ) ) {
		return false;
	}
	if ( 9 === color.length && '00' === color.slice( -2 ).toLowerCase() ) {
		return false;
	}
	if ( 5 === color.length && '0' === color.slice( -1 ) ) {
		return false;
	}
	return true;
}

function isSupportedFetchPriority( value?: string ): value is LazyImageFetchPriority {
	return 'high' === value || 'low' === value || 'auto' === value;
}

const resolvedPlaceholder = computed<LazyImagePlaceholder>( () => {
	const key = ( props.placeholder ?? 'none' ).toLowerCase() as LazyImagePlaceholder;
	return PLACEHOLDERS.includes( key ) ? key : 'none';
} );

const useSkeleton = computed( () => 'skeleton' === resolvedPlaceholder.value );

const useBlur = computed(
	() => 'blur' === resolvedPlaceholder.value && !! props.blurSrc && SAFE_BLUR_URI.test( props.blurSrc ),
);

const initialSrc = computed( () => ( useBlur.value ? ( props.blurSrc as string ) : props.src ) );

const bgStyle = computed( () => {
	if ( 'dominant_color' !== resolvedPlaceholder.value ) {
		return undefined;
	}
	if ( ! props.dominantColor || ! isValidHexColor( props.dominantColor ) ) {
		return undefined;
	}
	return { backgroundColor: props.dominantColor };
} );

const skeletonStyle = computed( () => {
	if ( ! useSkeleton.value ) {
		return undefined;
	}
	if ( undefined === props.width || undefined === props.height ) {
		return undefined;
	}
	return { aspectRatio: `${ props.width } / ${ props.height }` };
} );

const imgClass = computed( () => [ 'perf-lazy-image', props.className ].filter( Boolean ).join( ' ' ) );

const emittedFetchPriority = computed( () =>
	isSupportedFetchPriority( props.fetchPriority ) ? props.fetchPriority : undefined,
);
</script>

<template>
	<div v-if="useSkeleton" class="perf-skeleton" :style="skeletonStyle">
		<img
			:src="initialSrc"
			:data-src="useBlur ? props.src : undefined"
			:alt="props.alt"
			:loading="props.lazy ? 'lazy' : 'eager'"
			decoding="async"
			:width="props.width"
			:height="props.height"
			:srcset="props.srcset"
			:sizes="props.sizes"
			:fetchpriority="emittedFetchPriority"
			:data-threshold="props.threshold"
			:class="imgClass"
			:style="bgStyle"
		/>
	</div>
	<img
		v-else
		:src="initialSrc"
		:data-src="useBlur ? props.src : undefined"
		:alt="props.alt"
		:loading="props.lazy ? 'lazy' : 'eager'"
		decoding="async"
		:width="props.width"
		:height="props.height"
		:srcset="props.srcset"
		:sizes="props.sizes"
		:fetchpriority="emittedFetchPriority"
		:data-threshold="props.threshold"
		:class="imgClass"
		:style="bgStyle"
	/>
</template>
