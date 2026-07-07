<div class="performance-ai-panel" data-feature="performance.query_insight">
	@if ( ! $this->isEnabled )
		<p class="performance-ai-panel__disabled">
			{{ __( 'AI query insight is currently disabled.' ) }}
		</p>
	@else
		<div class="performance-ai-panel__fields">
			<label class="performance-ai-panel__label" for="query-insight-query">
				{{ __( 'Query' ) }}
			</label>
			<textarea
				id="query-insight-query"
				wire:model.live="query"
				class="performance-ai-panel__textarea"
				rows="5"
				placeholder="SELECT * FROM …"
			></textarea>

			<label class="performance-ai-panel__label" for="query-insight-explain">
				{{ __( 'EXPLAIN plan (optional)' ) }}
			</label>
			<textarea
				id="query-insight-explain"
				wire:model.live="explain"
				class="performance-ai-panel__textarea"
				rows="4"
				placeholder="EXPLAIN output …"
			></textarea>

			<label class="performance-ai-panel__label" for="query-insight-schema">
				{{ __( 'Relevant schema (optional, JSON or text)' ) }}
			</label>
			<textarea
				id="query-insight-schema"
				wire:model.live="schema"
				class="performance-ai-panel__textarea"
				rows="4"
				placeholder='{ "articles": { "id": "bigint", "slug": "varchar(191)" } }'
			></textarea>

			<div class="performance-ai-panel__meta">
				<label class="performance-ai-panel__label" for="query-insight-connection">
					{{ __( 'Connection' ) }}
				</label>
				<input
					id="query-insight-connection"
					type="text"
					wire:model.live="connection"
					class="performance-ai-panel__input"
					placeholder="mysql"
				/>

				<label class="performance-ai-panel__label" for="query-insight-time-ms">
					{{ __( 'Observed duration (ms)' ) }}
				</label>
				<input
					id="query-insight-time-ms"
					type="number"
					step="0.01"
					wire:model.live="timeMs"
					class="performance-ai-panel__input"
					placeholder="120.5"
				/>
			</div>
		</div>

		<button
			type="button"
			wire:click="analyze"
			wire:loading.attr="disabled"
			wire:target="analyze"
			@disabled( $isLoading || '' === trim( $query ) )
			class="performance-ai-panel__button"
		>
			<span wire:loading.remove wire:target="analyze">
				{{ __( 'Analyze query' ) }}
			</span>
			<span wire:loading wire:target="analyze">
				{{ __( 'Analyzing…' ) }}
			</span>
		</button>

		@if ( null !== $error )
			<p class="performance-ai-panel__error" role="alert">
				{{ $error }}
			</p>
		@endif

		@if ( null !== $insight )
			<div class="performance-ai-panel__result">
				@if ( ! empty( $insight['summary'] ) )
					<p class="performance-ai-panel__summary">{{ $insight['summary'] }}</p>
				@endif

				@if ( ! empty( $insight['bottlenecks'] ) )
					<h4 class="performance-ai-panel__heading">{{ __( 'Bottlenecks' ) }}</h4>
					<ol class="performance-ai-panel__list">
						@foreach ( $insight['bottlenecks'] as $bottleneck )
							<li>{{ $bottleneck }}</li>
						@endforeach
					</ol>
				@endif

				@if ( ! empty( $insight['suggested_indexes'] ) )
					<h4 class="performance-ai-panel__heading">{{ __( 'Suggested indexes' ) }}</h4>
					<ul class="performance-ai-panel__list">
						@foreach ( $insight['suggested_indexes'] as $index )
							<li>
								<code>{{ $index['table'] }}({{ implode( ', ', $index['columns'] ) }})</code>
								@if ( '' !== $index['rationale'] )
									<span class="performance-ai-panel__rationale">{{ $index['rationale'] }}</span>
								@endif
							</li>
						@endforeach
					</ul>
				@endif

				@if ( ! empty( $insight['rewrites'] ) )
					<h4 class="performance-ai-panel__heading">{{ __( 'Suggested rewrites' ) }}</h4>
					<ul class="performance-ai-panel__list">
						@foreach ( $insight['rewrites'] as $rewrite )
							<li>
								<div class="performance-ai-panel__rewrite">
									<div class="performance-ai-panel__rewrite-before">
										<span class="performance-ai-panel__rewrite-label">{{ __( 'Before' ) }}</span>
										<code>{{ $rewrite['original'] }}</code>
									</div>
									<div class="performance-ai-panel__rewrite-after">
										<span class="performance-ai-panel__rewrite-label">{{ __( 'After' ) }}</span>
										<code>{{ $rewrite['suggested'] }}</code>
									</div>
								</div>
								@if ( '' !== $rewrite['rationale'] )
									<span class="performance-ai-panel__rationale">{{ $rewrite['rationale'] }}</span>
								@endif
							</li>
						@endforeach
					</ul>
				@endif

				@if ( ! empty( $insight['caveats'] ) )
					<h4 class="performance-ai-panel__heading">{{ __( 'Caveats' ) }}</h4>
					<ul class="performance-ai-panel__list performance-ai-panel__list--muted">
						@foreach ( $insight['caveats'] as $caveat )
							<li>{{ $caveat }}</li>
						@endforeach
					</ul>
				@endif
			</div>
		@endif
	@endif
</div>
