<div class="performance-recommendations" data-testid="recommendations-panel">
    @isset($header)
        <div class="performance-recommendations__header">
            {{ $header }}
        </div>
    @endisset

    <div class="performance-recommendations__toolbar">
        <h2 class="performance-recommendations__heading">{{ $resolvedLabels['title'] }}</h2>

        @if($dismissedCount > 0)
            <button
                type="button"
                wire:click="resetDismissals"
                class="performance-recommendations__reset"
            >{{ $resolvedLabels['reset'] }} ({{ $dismissedCount }})</button>
        @endif
    </div>

    @if($statusMessage)
        <div
            @class([
                'performance-recommendations__status',
                'is-error' => $statusIsError,
                'is-success' => ! $statusIsError,
            ])
            role="status"
        >
            {{ $statusMessage }}
        </div>
    @endif

    @isset($beforeItems)
        {{ $beforeItems }}
    @endisset

    @if(empty($items))
        <p class="performance-recommendations__empty">{{ $resolvedLabels['empty'] }}</p>
    @else
        <ul class="performance-recommendations__list" role="list">
            @foreach($items as $item)
                @php
                    $priorityLabel = $resolvedLabels[ 'priority_' . $item['priority'] ] ?? $item['priority'];
                @endphp
                <li wire:key="rec-{{ $item['id'] }}" @class([
                    'performance-recommendations__item',
                    'is-high' => $item['priority'] === 'high',
                    'is-medium' => $item['priority'] === 'medium',
                    'is-low' => $item['priority'] === 'low',
                ])>
                    <div class="performance-recommendations__item-head">
                        <span class="performance-recommendations__priority">{{ $priorityLabel }}</span>
                        <strong class="performance-recommendations__title">{{ $item['title'] }}</strong>
                    </div>

                    @if(!empty($item['description']))
                        <p class="performance-recommendations__description">{{ $item['description'] }}</p>
                    @endif

                    @if(!empty($item['manual_steps']))
                        <details class="performance-recommendations__steps">
                            <summary>{{ $resolvedLabels['manual_steps'] }}</summary>
                            <ol>
                                @foreach($item['manual_steps'] as $step)
                                    <li>{{ $step }}</li>
                                @endforeach
                            </ol>
                        </details>
                    @endif

                    <div class="performance-recommendations__actions">
                        @if(!empty($item['action']))
                            <button
                                type="button"
                                wire:click="applyAction({{ \Illuminate\Support\Js::from( $item['id'] ) }})"
                                class="performance-recommendations__apply"
                            >{{ $resolvedLabels['apply'] }}</button>
                        @endif

                        <button
                            type="button"
                            wire:click="dismiss({{ \Illuminate\Support\Js::from( $item['id'] ) }})"
                            class="performance-recommendations__dismiss"
                        >{{ $resolvedLabels['dismiss'] }}</button>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    @isset($afterItems)
        {{ $afterItems }}
    @endisset

    @isset($footer)
        <div class="performance-recommendations__footer">
            {{ $footer }}
        </div>
    @endisset
</div>
