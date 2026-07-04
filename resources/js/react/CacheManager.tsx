/**
 * CacheManager — React port of the `CacheManager` Livewire component.
 *
 * Loads the current page + fragment cache snapshot from the admin API and
 * exposes destructive actions with a confirmation gate.
 *
 * @since 1.0.0
 */

import { useCallback, useEffect, useState, type JSX } from 'react';
import type { CachePayload } from '../performance';
import { usePerformance, type UsePerformanceOptions } from './usePerformance';

export interface CacheManagerProps extends UsePerformanceOptions {
	className?: string;
}

type PendingAction = null | 'flush' | 'warm' | { kind: 'entry'; index: number } | { kind: 'tag'; index: number } | { kind: 'key'; value: string };

export function CacheManager( props: CacheManagerProps ): JSX.Element {
	const { className, ...hookOptions } = props;
	const { loadCache, runCacheAction } = usePerformance( hookOptions );
	const [ payload, setPayload ] = useState<CachePayload | null>( null );
	const [ loading, setLoading ] = useState( true );
	const [ pending, setPending ] = useState<PendingAction>( null );
	const [ keyInput, setKeyInput ] = useState( '' );
	const [ status, setStatus ] = useState<{ message: string; isError: boolean } | null>( null );
	const [ working, setWorking ] = useState( false );

	const refresh = useCallback( async () => {
		setLoading( true );
		try {
			setPayload( await loadCache() );
		} finally {
			setLoading( false );
		}
	}, [ loadCache ] );

	useEffect( () => {
		void refresh();
	}, [ refresh ] );

	const runAction = useCallback(
		async ( action: 'flush' | 'warm' | 'invalidate-key' | 'invalidate-tag', payload: { key?: string; tag?: string } = {} ) => {
			setWorking( true );
			try {
				const result = await runCacheAction( action, payload );
				setStatus( { message: result.message, isError: result.is_error } );
				setPayload( { summary: result.summary!, page_entries: result.page_entries ?? [], fragment_tags: result.fragment_tags ?? [] } );
			} catch ( err ) {
				setStatus( {
					message: err instanceof Error ? err.message : String( err ),
					isError: true,
				} );
			} finally {
				setWorking( false );
				setPending( null );
			}
		},
		[ runCacheAction ],
	);

	if ( loading || null === payload ) {
		return <div className="performance-cache-manager" data-testid="performance-cache-manager">Loading…</div>;
	}

	const { summary, page_entries, fragment_tags } = payload;

	const isEntryPending = ( index: number ): boolean =>
		null !== pending && 'object' === typeof pending && 'entry' === pending.kind && pending.index === index;

	const isTagPending = ( index: number ): boolean =>
		null !== pending && 'object' === typeof pending && 'tag' === pending.kind && pending.index === index;

	const isKeyPending = ( value: string ): boolean =>
		null !== pending && 'object' === typeof pending && 'key' === pending.kind && pending.value === value;

	return (
		<div className={ [ 'performance-cache-manager', className ].filter( Boolean ).join( ' ' ) } data-testid="performance-cache-manager">
			{ status && (
				<div
					role={ status.isError ? 'alert' : 'status' }
					className={ 'performance-cache-manager__status' + ( status.isError ? ' is-error' : '' ) }
					data-testid="status-banner"
				>
					{ status.message }
				</div>
			) }

			<section>
				<h3>Page Cache</h3>
				<dl>
					<dt>Entries</dt>
					<dd>{ summary.page.entries }</dd>
					<dt>Size</dt>
					<dd>{ summary.page.size_bytes ?? 'N/A' }</dd>
					<dt>Hit rate</dt>
					<dd>{ summary.page.hit_rate ?? 'N/A' }</dd>
				</dl>

				<form
					onSubmit={ ( event ) => {
						event.preventDefault();
						const value = keyInput.trim();
						if ( '' !== value ) {
							setPending( { kind: 'key', value } );
						}
					} }
				>
					<label>
						Invalidate key or pattern
						<input value={ keyInput } onChange={ ( e ) => setKeyInput( e.target.value ) } />
					</label>
					<button type="submit" disabled={ working }>Request invalidation</button>
				</form>

				{ isKeyPending( keyInput.trim() ) && (
					<div>
						<p>Invalidate <code>{ keyInput }</code>?</p>
						<button
							type="button"
							onClick={ () => void runAction( 'invalidate-key', { key: keyInput.trim() } ) }
							disabled={ working }
						>Confirm</button>
						<button type="button" onClick={ () => setPending( null ) }>Cancel</button>
					</div>
				) }

				<table>
					<thead>
						<tr>
							<th>Path</th>
							<th />
						</tr>
					</thead>
					<tbody>
						{ page_entries.map( ( entry, index ) => (
							<tr key={ entry.key }>
								<td>{ entry.path }</td>
								<td>
									{ isEntryPending( index ) ? (
										<>
											<button
												type="button"
												onClick={ () => void runAction( 'invalidate-key', { key: entry.path } ) }
												disabled={ working }
											>Confirm</button>
											<button type="button" onClick={ () => setPending( null ) }>Cancel</button>
										</>
									) : (
										<button type="button" onClick={ () => setPending( { kind: 'entry', index } ) }>
											Invalidate
										</button>
									) }
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			</section>

			<section>
				<h3>Fragment Cache</h3>
				<dl>
					<dt>Entries</dt>
					<dd>{ summary.fragment.entries }</dd>
					<dt>Tags</dt>
					<dd>{ summary.fragment.tags }</dd>
					<dt>Hit rate</dt>
					<dd>{ summary.fragment.hit_rate ?? 'N/A' }</dd>
				</dl>

				<table>
					<thead>
						<tr>
							<th>Tag</th>
							<th>Entries</th>
							<th />
						</tr>
					</thead>
					<tbody>
						{ fragment_tags.map( ( row, index ) => (
							<tr key={ row.tag }>
								<td>{ row.tag }</td>
								<td>{ row.entry_count }</td>
								<td>
									{ isTagPending( index ) ? (
										<>
											<button
												type="button"
												onClick={ () => void runAction( 'invalidate-tag', { tag: row.tag } ) }
												disabled={ working }
											>Confirm</button>
											<button type="button" onClick={ () => setPending( null ) }>Cancel</button>
										</>
									) : (
										<button type="button" onClick={ () => setPending( { kind: 'tag', index } ) }>
											Invalidate
										</button>
									) }
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			</section>

			<section>
				<h3>Actions</h3>
				<button type="button" data-testid="warm-cache" onClick={ () => void runAction( 'warm' ) } disabled={ working }>
					Warm cache
				</button>
				{ 'flush' === pending ? (
					<>
						<button type="button" data-testid="confirm-flush" onClick={ () => void runAction( 'flush' ) } disabled={ working }>
							Confirm flush
						</button>
						<button type="button" onClick={ () => setPending( null ) }>Cancel</button>
					</>
				) : (
					<button type="button" data-testid="request-flush" onClick={ () => setPending( 'flush' ) }>
						Flush all
					</button>
				) }
			</section>
		</div>
	);
}
