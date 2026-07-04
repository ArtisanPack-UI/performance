/**
 * PerfPrefetch — React component that injects `<link rel="prefetch">`
 * entries into the document head for browsers without the Speculation
 * Rules API.
 *
 * @since 1.0.0
 */

import { useEffect, type JSX } from 'react';

export interface PerfPrefetchProps {
	urls: string | string[];
	as?: 'document' | 'script' | 'style' | 'image' | 'font' | 'fetch';
}

function normalizeUrls( input: string | string[] ): string[] {
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

export function PerfPrefetch( { urls, as }: PerfPrefetchProps ): JSX.Element | null {
	useEffect( () => {
		if ( typeof document === 'undefined' ) {
			return undefined;
		}
		const normalized = normalizeUrls( urls );
		const elements: HTMLLinkElement[] = normalized.map( ( href ) => {
			const link = document.createElement( 'link' );
			link.rel = 'prefetch';
			link.href = href;
			if ( as ) {
				link.setAttribute( 'as', as );
			}
			document.head.appendChild( link );
			return link;
		} );
		return () => {
			elements.forEach( ( link ) => link.remove() );
		};
	}, [ urls, as ] );

	return null;
}
