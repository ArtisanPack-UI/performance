<div class="performance-ai-panel" data-feature="performance.optimization_suggestion">
	@if ( ! $this->isEnabled )
		<p class="performance-ai-panel__disabled">
			{{ __( 'AI optimization suggestions are currently disabled.' ) }}
		</p>
	@else
		<div class="performance-ai-panel__fields">
			<label class="performance-ai-panel__label" for="optimization-window">
				{{ __( 'Window (days)' ) }}
			</label>
			<input
				id="optimization-window"
				type="number"
				min="1"
				max="90"
				wire:model.live="windowDays"
				class="performance-ai-panel__input"
			/>

			<label class="performance-ai-panel__label" for="optimization-priority">
				{{ __( 'Business priority (optional)' ) }}
			</label>
			<input
				id="optimization-priority"
				type="text"
				wire:model.live="businessPriority"
				class="performance-ai-panel__input"
				placeholder="checkout > blog"
			/>

			<label class="performance-ai-panel__label" for="optimization-recent">
				{{ __( 'Recent changes (optional)' ) }}
			</label>
			<textarea
				id="optimization-recent"
				wire:model.live="recentChanges"
				class="performance-ai-panel__textarea"
				rows="2"
				placeholder="switched to Vite last week"
			></textarea>
		</div>

		<button
			type="button"
			wire:click="suggest"
			wire:loading.attr="disabled"
			wire:target="suggest"
			@disabled( $isLoading )
			class="performance-ai-panel__button"
		>
			<span wire:loading.remove wire:target="suggest">
				{{ __( 'Suggest optimizations' ) }}
			</span>
			<span wire:loading wire:target="suggest">
				{{ __( 'Analyzing…' ) }}
			</span>
		</button>

		@if ( null !== $error )
			<p class="performance-ai-panel__error" role="alert">
				{{ $error }}
			</p>
		@endif

		@if ( null !== $suggestion )
			<div class="performance-ai-panel__result">
				@if ( ! empty( $suggestion['summary'] ) )
					<p class="performance-ai-panel__summary">{{ $suggestion['summary'] }}</p>
				@endif

				@if ( ! empty( $suggestion['focus_areas'] ) )
					<h4 class="performance-ai-panel__heading">{{ __( 'Focus areas' ) }}</h4>
					<ul class="performance-ai-panel__list">
						@foreach ( $suggestion['focus_areas'] as $area )
							<li class="performance-ai-panel__focus-area">
								<div class="performance-ai-panel__focus-area-header">
									<strong>{{ $area['title'] }}</strong>
									<span class="performance-ai-panel__tag performance-ai-panel__tag--impact-{{ $area['impact'] }}">
										{{ __( 'Impact' ) }}: {{ $area['impact'] }}
									</span>
									<span class="performance-ai-panel__tag performance-ai-panel__tag--effort-{{ $area['effort'] }}">
										{{ __( 'Effort' ) }}: {{ $area['effort'] }}
									</span>
								</div>

								@if ( ! empty( $area['routes'] ) )
									<p class="performance-ai-panel__routes">
										{{ __( 'Routes' ) }}:
										@foreach ( $area['routes'] as $route )
											<code>{{ $route }}</code>@if ( ! $loop->last ), @endif
										@endforeach
									</p>
								@endif

								@if ( '' !== $area['rationale'] )
									<p class="performance-ai-panel__rationale">{{ $area['rationale'] }}</p>
								@endif

								@if ( ! empty( $area['actions'] ) )
									<ul class="performance-ai-panel__actions">
										@foreach ( $area['actions'] as $action )
											<li>{{ $action }}</li>
										@endforeach
									</ul>
								@endif
							</li>
						@endforeach
					</ul>
				@endif

				@if ( ! empty( $suggestion['quick_wins'] ) )
					<h4 class="performance-ai-panel__heading">{{ __( 'Quick wins' ) }}</h4>
					<ul class="performance-ai-panel__list">
						@foreach ( $suggestion['quick_wins'] as $win )
							<li>{{ $win }}</li>
						@endforeach
					</ul>
				@endif

				@if ( ! empty( $suggestion['caveats'] ) )
					<h4 class="performance-ai-panel__heading">{{ __( 'Caveats' ) }}</h4>
					<ul class="performance-ai-panel__list performance-ai-panel__list--muted">
						@foreach ( $suggestion['caveats'] as $caveat )
							<li>{{ $caveat }}</li>
						@endforeach
					</ul>
				@endif
			</div>
		@endif
	@endif
</div>
