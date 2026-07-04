/**
 * LazyImage — React port of the Blade `<x-perf-lazy-image>` component.
 *
 * Renders an `<img>` element with native lazy loading and one of four
 * placeholder strategies: `dominant_color`, `blur`, `skeleton`, `none`.
 * Width/height are strongly recommended to prevent CLS.
 *
 * @since 1.0.0
 */

import type { CSSProperties, ImgHTMLAttributes, JSX } from 'react';

export type LazyImagePlaceholder = 'dominant_color' | 'blur' | 'skeleton' | 'none';

export type LazyImageFetchPriority = 'high' | 'low' | 'auto';

export interface LazyImageProps
	extends Omit<
		ImgHTMLAttributes<HTMLImageElement>,
		'src' | 'alt' | 'loading' | 'width' | 'height' | 'fetchPriority' | 'placeholder' | 'srcSet' | 'sizes'
	> {
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
	srcSet?: string;
	className?: string;
}

const PLACEHOLDERS: readonly LazyImagePlaceholder[] = [ 'dominant_color', 'blur', 'skeleton', 'none' ] as const;

const SAFE_BLUR_URI = /^data:image\/(?:jpeg|png|webp|avif|gif)[;,]/i;

const HEX_COLOR = /^#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/;

function resolvePlaceholder( value?: string ): LazyImagePlaceholder {
	const key = ( value ?? 'none' ).toLowerCase() as LazyImagePlaceholder;
	return PLACEHOLDERS.includes( key ) ? key : 'none';
}

function isSafeBlurDataUri( uri: string ): boolean {
	return SAFE_BLUR_URI.test( uri );
}

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

export function LazyImage( props: LazyImageProps ): JSX.Element {
	const {
		src,
		alt = '',
		width,
		height,
		lazy = true,
		placeholder,
		dominantColor,
		blurSrc,
		fetchPriority,
		threshold,
		sizes,
		srcSet,
		className,
		style,
		...rest
	} = props;

	const resolved = resolvePlaceholder( placeholder );
	const useSkeleton = 'skeleton' === resolved;
	const useBlur = 'blur' === resolved && !! blurSrc && isSafeBlurDataUri( blurSrc );
	const initialSrc = useBlur ? ( blurSrc as string ) : src;

	const bgStyle: CSSProperties | undefined =
		'dominant_color' === resolved && dominantColor && isValidHexColor( dominantColor )
			? { backgroundColor: dominantColor }
			: undefined;

	const combinedClassName = [ 'perf-lazy-image', className ].filter( Boolean ).join( ' ' );

	const img = (
		<img
			{ ...rest }
			src={ initialSrc }
			data-src={ useBlur ? src : undefined }
			alt={ alt }
			loading={ lazy ? 'lazy' : 'eager' }
			decoding="async"
			width={ width }
			height={ height }
			srcSet={ srcSet }
			sizes={ sizes }
			fetchPriority={ isSupportedFetchPriority( fetchPriority ) ? fetchPriority : undefined }
			data-threshold={ threshold }
			className={ combinedClassName }
			style={ { ...bgStyle, ...style } }
		/>
	);

	if ( useSkeleton ) {
		const skeletonStyle: CSSProperties | undefined =
			undefined !== width && undefined !== height ? { aspectRatio: `${ width } / ${ height }` } : undefined;
		return (
			<div className="perf-skeleton" style={ skeletonStyle }>
				{ img }
			</div>
		);
	}

	return img;
}
