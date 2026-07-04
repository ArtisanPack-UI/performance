/**
 * SpeculativeRules — React component that injects a
 * `<script type="speculationrules">` into the document head.
 *
 * Feature-detects the Speculation Rules API before mounting. Cleans up
 * on unmount so a route change doesn't leave stale rules stacked in
 * `<head>`.
 *
 * @since 1.0.0
 */

import { useEffect, type JSX } from 'react';

export interface SpeculationRules {
	prefetch?: unknown;
	prerender?: unknown;
}

export interface SpeculativeRulesProps {
	rules: SpeculationRules | string;
}

function supportsSpeculationRules(): boolean {
	if ( typeof window === 'undefined' || typeof HTMLScriptElement === 'undefined' ) {
		return false;
	}
	const supports = ( HTMLScriptElement as unknown as { supports?: ( key: string ) => boolean } ).supports;
	return 'function' === typeof supports ? supports.call( HTMLScriptElement, 'speculationrules' ) : false;
}

export function SpeculativeRules( { rules }: SpeculativeRulesProps ): JSX.Element | null {
	useEffect( () => {
		if ( ! supportsSpeculationRules() ) {
			return undefined;
		}
		const json = 'string' === typeof rules ? rules : JSON.stringify( rules );
		if ( '' === json || '{}' === json ) {
			return undefined;
		}
		const script = document.createElement( 'script' );
		script.type = 'speculationrules';
		script.textContent = json;
		document.head.appendChild( script );
		return () => {
			script.remove();
		};
	}, [ rules ] );

	return null;
}
