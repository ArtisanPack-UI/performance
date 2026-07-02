<div class="performance-query-analyzer" data-testid="query-analyzer">
    @isset($header)
        <div class="performance-query-analyzer__header">
            {{ $header }}
        </div>
    @endisset

    <div class="performance-query-analyzer__toolbar">
        <h2 class="performance-query-analyzer__heading">{{ $resolvedLabels['title'] }}</h2>

        <div class="performance-query-analyzer__filters">
            <label class="performance-query-analyzer__filter">
                <span>{{ $resolvedLabels['range'] }}</span>
                <select wire:model.live="dateRange">
                    @foreach($ranges as $range)
                        <option value="{{ $range }}">{{ $range }}</option>
                    @endforeach
                </select>
            </label>

            <label class="performance-query-analyzer__filter">
                <span>{{ $resolvedLabels['route'] }}</span>
                <select wire:model.live="routeFilter">
                    <option value="">{{ $resolvedLabels['all_routes'] }}</option>
                    @foreach($availableRoutes as $availableRoute)
                        <option value="{{ $availableRoute }}">{{ $availableRoute }}</option>
                    @endforeach
                </select>
            </label>

            <label class="performance-query-analyzer__filter">
                <span>{{ $resolvedLabels['min_time'] }}</span>
                <input type="number" min="0" step="10" wire:model.live.debounce.300ms="minTimeMs" />
            </label>

            <div class="performance-query-analyzer__sort" role="group">
                <button
                    type="button"
                    wire:click="setSort('time')"
                    @class([
                        'performance-query-analyzer__sort-button',
                        'is-active' => $sort === 'time',
                    ])
                    aria-pressed="{{ $sort === 'time' ? 'true' : 'false' }}"
                >{{ $resolvedLabels['sort_time'] }}</button>
                <button
                    type="button"
                    wire:click="setSort('frequency')"
                    @class([
                        'performance-query-analyzer__sort-button',
                        'is-active' => $sort === 'frequency',
                    ])
                    aria-pressed="{{ $sort === 'frequency' ? 'true' : 'false' }}"
                >{{ $resolvedLabels['sort_freq'] }}</button>
            </div>

            <button
                type="button"
                wire:click="exportCsv"
                class="performance-query-analyzer__export"
            >{{ $resolvedLabels['export'] }}</button>
        </div>
    </div>

    @isset($beforeTable)
        {{ $beforeTable }}
    @endisset

    @if(empty($rows))
        <p class="performance-query-analyzer__empty">{{ $resolvedLabels['empty'] }}</p>
    @else
        <table class="performance-query-analyzer__table" data-testid="query-analyzer-table">
            <thead>
                <tr>
                    <th>{{ $resolvedLabels['query'] }}</th>
                    <th>{{ $resolvedLabels['time'] }}</th>
                    <th>{{ $resolvedLabels['count'] }}</th>
                    <th>{{ $resolvedLabels['route'] }}</th>
                    <th>{{ $resolvedLabels['file'] }}</th>
                    <th>{{ $resolvedLabels['suggestion'] }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr wire:key="query-{{ $row['hash'] }}">
                        <td>
                            <code class="performance-query-analyzer__query">
                                @if($expandedSignature === $row['hash'])
                                    {{ $row['query'] }}
                                @else
                                    {{ \Illuminate\Support\Str::limit( $row['query'], 120 ) }}
                                @endif
                            </code>
                            <button
                                type="button"
                                wire:click="toggleExpanded({{ \Illuminate\Support\Js::from( $row['hash'] ) }})"
                                class="performance-query-analyzer__toggle"
                            >{{ $expandedSignature === $row['hash'] ? $resolvedLabels['hide_full'] : $resolvedLabels['show_full'] }}</button>
                        </td>
                        <td>
                            <div>{{ number_format( $row['peak_time_ms'], 2 ) }}</div>
                            <small>{{ __( 'avg :ms', [ 'ms' => number_format( $row['avg_time_ms'], 2 ) ] ) }}</small>
                        </td>
                        <td>{{ number_format( $row['occurrences'] ) }}</td>
                        <td>{{ $row['route'] ?? '—' }}</td>
                        <td>
                            @if($row['file'])
                                <code>{{ $row['file'] }}{{ $row['line'] ? ':' . $row['line'] : '' }}</code>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $row['suggestion'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @isset($footer)
        <div class="performance-query-analyzer__footer">
            {{ $footer }}
        </div>
    @endisset
</div>
