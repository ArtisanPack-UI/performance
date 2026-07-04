<script setup lang="ts">
/**
 * ResponsiveImage — Vue port of the Blade `<x-perf-responsive-image>`.
 *
 * @since 1.0.0
 */

import { computed } from 'vue';
import LazyImage, { type LazyImageFetchPriority, type LazyImagePlaceholder } from './LazyImage.vue';

interface ResponsiveImageProps {
	src: string;
	alt?: string;
	width?: number;
	height?: number;
	lazy?: boolean;
	placeholder?: LazyImagePlaceholder;
	dominantColor?: string;
	fetchPriority?: LazyImageFetchPriority;
	avifSrcset?: string;
	webpSrcset?: string;
	fallbackSrcset?: string;
	sizesAttr?: string;
	className?: string;
	imgClassName?: string;
}

const props = withDefaults( defineProps<ResponsiveImageProps>(), {
	alt: '',
	lazy: true,
	avifSrcset: '',
	webpSrcset: '',
	fallbackSrcset: '',
} );

const pictureClass = computed( () =>
	[ 'perf-responsive-image', props.className ].filter( Boolean ).join( ' ' ),
);
</script>

<template>
	<picture :class="pictureClass">
		<source
			v-if="'' !== props.avifSrcset"
			type="image/avif"
			:srcset="props.avifSrcset"
			:sizes="props.sizesAttr"
		/>
		<source
			v-if="'' !== props.webpSrcset"
			type="image/webp"
			:srcset="props.webpSrcset"
			:sizes="props.sizesAttr"
		/>
		<LazyImage
			:src="props.src"
			:alt="props.alt"
			:width="props.width"
			:height="props.height"
			:lazy="props.lazy"
			:placeholder="props.placeholder"
			:dominant-color="props.dominantColor"
			:fetch-priority="props.fetchPriority"
			:sizes="props.sizesAttr"
			:srcset="'' !== props.fallbackSrcset ? props.fallbackSrcset : undefined"
			:class-name="props.imgClassName"
		/>
	</picture>
</template>
