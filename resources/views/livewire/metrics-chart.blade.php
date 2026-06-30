<div class="performance-metrics-chart" data-testid="performance-metrics-chart">
    <div class="performance-metrics-chart__range" role="group" aria-label="{{ __( 'Chart date range' ) }}">
        @foreach(array_keys(\ArtisanPackUI\Performance\Livewire\MetricsChart::RANGE_DAYS) as $rangeKey)
            <button
                type="button"
                wire:click="setDateRange('{{ $rangeKey }}')"
                @class([
                    'performance-metrics-chart__range-button',
                    'is-active' => $dateRange === $rangeKey,
                ])
                aria-pressed="{{ $dateRange === $rangeKey ? 'true' : 'false' }}"
            >{{ $rangeKey }}</button>
        @endforeach
    </div>

    <div
        class="performance-metrics-chart__canvas"
        data-metrics-chart
        data-chart-payload="{{ json_encode( $chartPayload, JSON_THROW_ON_ERROR ) }}"
    >
        <canvas role="img" aria-label="{{ __( 'Metrics chart' ) }}"></canvas>
    </div>

    <details class="performance-metrics-chart__data">
        <summary>{{ __( 'View data' ) }}</summary>

        <table class="performance-metrics-chart__table">
            <thead>
                <tr>
                    <th>{{ __( 'Date' ) }}</th>
                    @foreach($chartPayload['datasets'] as $dataset)
                        <th>{{ $dataset['metric'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($chartPayload['labels'] as $index => $label)
                    <tr wire:key="metrics-chart-row-{{ $label }}">
                        <td>{{ $label }}</td>
                        @foreach($chartPayload['datasets'] as $dataset)
                            <td>{{ $dataset['values'][ $index ] !== null ? number_format( $dataset['values'][ $index ], 2 ) : '—' }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </details>
</div>
