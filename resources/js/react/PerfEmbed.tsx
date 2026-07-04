/**
 * PerfEmbed — React port of the Blade `<x-perf-embed>` component.
 *
 * The Blade component resolves the facade via `EmbedOptimizer` on the
 * server. In React the facade shape must be resolved by the host app (from
 * the server, from build-time data, or from a small fetch) and passed as
 * the `facade` prop. When `facade` is null the component emits nothing;
 * when `lazy=true` it renders a thumbnail + play button that swaps in the
 * real iframe/blockquote on click. Matches the bundled
 * `speculative-rules.js` data-attribute contract so either approach works.
 *
 * @since 1.0.0
 */

import { useMemo, useState, type CSSProperties, type JSX } from 'react';

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

export interface PerfEmbedProps {
	facade: PerfEmbedFacade | null;
	lazy?: boolean;
	showFacade?: boolean;
	className?: string;
	width?: number;
	height?: number;
	error?: string;
	onActivate?: ( facade: PerfEmbedFacade ) => void;
}

function PlayButton( { title, onActivate }: { title: string; onActivate: () => void } ): JSX.Element {
	return (
		<button
			type="button"
			className="perf-embed-play"
			aria-label={ title }
			onClick={ ( event ) => {
				// Handle here (not just via bubble to the parent div) so the
				// component keeps working if a consumer swaps out the outer
				// facade markup — the button's own click is the source of truth.
				event.stopPropagation();
				onActivate();
			} }
		>
			<svg viewBox="0 0 24 24" width={ 48 } height={ 48 } aria-hidden="true" focusable="false">
				<path d="M8 5v14l11-7z" fill="currentColor" />
			</svg>
		</button>
	);
}

export function PerfEmbed( props: PerfEmbedProps ): JSX.Element | null {
	const { facade, lazy = true, showFacade = true, className, width, height, error, onActivate } = props;
	const [ activated, setActivated ] = useState( false );

	const containerClass = useMemo(
		() => [ 'perf-embed', className ].filter( Boolean ).join( ' ' ),
		[ className ],
	);

	const style: CSSProperties | undefined = useMemo(
		() =>
			undefined !== width && undefined !== height ? { aspectRatio: `${ width } / ${ height }` } : undefined,
		[ width, height ],
	);

	if ( null === facade ) {
		return (
			<>
				{ /* mirrors the Blade `<!-- perf-embed: {reason} -->` no-op */ }
				{ '' !== ( error ?? '' ) ? null : null }
			</>
		);
	}

	const eager = ! lazy;

	if ( eager || activated ) {
		if ( 'blockquote' === facade.mode ) {
			return (
				<div className={ containerClass } style={ style }>
					<div dangerouslySetInnerHTML={ { __html: facade.embed_html } } />
					{ '' !== facade.widgets_script && (
						<script async src={ facade.widgets_script } charSet="utf-8" />
					) }
				</div>
			);
		}
		return (
			<iframe
				src={ facade.iframe_url }
				title={ facade.title }
				loading="eager"
				width={ width }
				height={ height }
				allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
				referrerPolicy="strict-origin-when-cross-origin"
				allowFullScreen
				className={ containerClass }
			/>
		);
	}

	const activate = (): void => {
		setActivated( true );
		onActivate?.( facade );
	};

	const facadeClass = `${ containerClass } perf-embed-facade`;

	if ( ! showFacade ) {
		return (
			<div
				className={ facadeClass }
				data-provider={ facade.provider }
				data-id={ facade.id }
				data-mode={ facade.mode }
				data-title={ facade.title }
				data-iframe-url={ facade.iframe_url }
				data-widgets-script={ facade.widgets_script }
				style={ style }
				onClick={ activate }
				onKeyDown={ ( event ) => ( 'Enter' === event.key || ' ' === event.key ) && activate() }
				role="button"
				tabIndex={ 0 }
				aria-label={ facade.title }
			/>
		);
	}

	return (
		<div
			className={ facadeClass }
			data-provider={ facade.provider }
			data-id={ facade.id }
			data-mode={ facade.mode }
			data-title={ facade.title }
			data-iframe-url={ facade.iframe_url }
			data-widgets-script={ facade.widgets_script }
			style={ style }
			onClick={ activate }
		>
			{ '' !== facade.thumbnail ? (
				<img
					src={ facade.thumbnail }
					alt={ facade.title }
					loading="lazy"
					decoding="async"
					className="perf-embed-thumbnail"
				/>
			) : (
				<div className="perf-embed-thumbnail perf-embed-thumbnail--placeholder" aria-hidden="true" />
			) }
			<PlayButton title={ facade.title } onActivate={ activate } />
		</div>
	);
}
