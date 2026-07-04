/**
 * ResponsiveImage — React port of the Blade `<x-perf-responsive-image>` component.
 *
 * The Blade side resolves srcset values against a filesystem-aware generator.
 * That generator can't run in the browser, so the React port accepts the
 * precomputed AVIF/WebP/fallback srcset strings as props — typically emitted
 * by a build step or a Blade helper on the server and hydrated into the
 * React tree.
 *
 * @since 1.0.0
 */

import type { HTMLAttributes, JSX } from 'react';
import { LazyImage, type LazyImageFetchPriority, type LazyImagePlaceholder } from './LazyImage';

export interface ResponsiveImageProps extends Omit<HTMLAttributes<HTMLElement>, 'placeholder'> {
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

export function ResponsiveImage( props: ResponsiveImageProps ): JSX.Element {
	const {
		src,
		alt = '',
		width,
		height,
		lazy = true,
		placeholder,
		dominantColor,
		fetchPriority,
		avifSrcset = '',
		webpSrcset = '',
		fallbackSrcset = '',
		sizesAttr,
		className,
		imgClassName,
		...rest
	} = props;

	const combinedClassName = [ 'perf-responsive-image', className ].filter( Boolean ).join( ' ' );

	return (
		<picture { ...rest } className={ combinedClassName }>
			{ '' !== avifSrcset && (
				<source type="image/avif" srcSet={ avifSrcset } sizes={ sizesAttr } />
			) }
			{ '' !== webpSrcset && (
				<source type="image/webp" srcSet={ webpSrcset } sizes={ sizesAttr } />
			) }
			<LazyImage
				src={ src }
				alt={ alt }
				width={ width }
				height={ height }
				lazy={ lazy }
				placeholder={ placeholder }
				dominantColor={ dominantColor }
				fetchPriority={ fetchPriority }
				sizes={ sizesAttr }
				srcSet={ '' !== fallbackSrcset ? fallbackSrcset : undefined }
				className={ imgClassName }
			/>
		</picture>
	);
}
