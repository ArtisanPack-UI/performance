<div class="performance-dashboard" data-testid="performance-dashboard">
    @isset($header)
        <div class="performance-dashboard__header">
            {{ $header }}
        </div>
    @endisset

    @php
        $rangeLabels = [
            '24h' => __( 'Last 24 hours' ),
            '7d'  => __( 'Last 7 days' ),
            '30d' => __( 'Last 30 days' ),
            '90d' => __( 'Last 90 days' ),
        ];
        $tabLabels = [
            'overview'        => __( 'Overview' ),
            'pages'           => __( 'Pages' ),
            'images'          => __( 'Images' ),
            'cache'           => __( 'Cache' ),
            'queries'         => __( 'Queries' ),
            'recommendations' => __( 'Recommendations' ),
        ];
        $statusLabels = [
            'good'              => __( 'Good' ),
            'needs-improvement' => __( 'Needs improvement' ),
            'poor'              => __( 'Poor' ),
            'unknown'           => __( 'Unknown' ),
        ];
    @endphp

    <div class="performance-dashboard__toolbar">
        <div class="performance-dashboard__range" role="group" aria-label="{{ __( 'Date range' ) }}">
            @foreach($ranges as $range)
                <button
                    type="button"
                    wire:click="setDateRange('{{ $range }}')"
                    @class([
                        'performance-dashboard__range-button',
                        'is-active' => $dateRange === $range,
                    ])
                    aria-pressed="{{ $dateRange === $range ? 'true' : 'false' }}"
                >
                    {{ $rangeLabels[$range] ?? $range }}
                </button>
            @endforeach
        </div>

        <button
            type="button"
            wire:click="refreshMetrics"
            class="performance-dashboard__refresh"
        >
            {{ __( 'Refresh' ) }}
        </button>
    </div>

    <div class="performance-dashboard__tabs" role="tablist">
        @foreach($tabs as $tab)
            <button
                type="button"
                role="tab"
                wire:click="setTab('{{ $tab }}')"
                aria-selected="{{ $activeTab === $tab ? 'true' : 'false' }}"
                @class([
                    'performance-dashboard__tab',
                    'is-active' => $activeTab === $tab,
                ])
            >
                {{ $tabLabels[$tab] ?? $tab }}
            </button>
        @endforeach
    </div>

    <div class="performance-dashboard__panel" role="tabpanel" wire:key="panel-{{ $activeTab }}">
        @if($activeTab === 'overview')
            <h2 class="performance-dashboard__heading">{{ __( 'Core Web Vitals' ) }}</h2>

            @if(empty($overview))
                <p class="performance-dashboard__empty">{{ __( 'No metrics recorded for this range yet.' ) }}</p>
            @else
                <table class="performance-dashboard__table" data-testid="overview-table">
                    <thead>
                        <tr>
                            <th>{{ __( 'Metric' ) }}</th>
                            <th>{{ __( 'p75' ) }}</th>
                            <th>{{ __( 'Samples' ) }}</th>
                            <th>{{ __( 'Status' ) }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($overview as $row)
                            <tr wire:key="overview-{{ $row['metric'] }}">
                                <td>{{ $row['metric'] }}</td>
                                <td>{{ $row['p75'] !== null ? number_format( $row['p75'], 2 ) : '—' }}</td>
                                <td>{{ number_format( $row['sample_count'] ) }}</td>
                                <td>
                                    <span @class([
                                        'performance-dashboard__badge',
                                        'is-good' => $row['status'] === 'good',
                                        'is-warning' => $row['status'] === 'needs-improvement',
                                        'is-danger' => $row['status'] === 'poor',
                                        'is-muted' => $row['status'] === 'unknown',
                                    ])>{{ $statusLabels[$row['status']] ?? $row['status'] }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @elseif($activeTab === 'pages')
            <h2 class="performance-dashboard__heading">{{ __( 'Pages' ) }}</h2>

            @if(empty($pages))
                <p class="performance-dashboard__empty">{{ __( 'No per-route metrics recorded yet.' ) }}</p>
            @else
                <table class="performance-dashboard__table" data-testid="pages-table">
                    <thead>
                        <tr>
                            <th>{{ __( 'Route' ) }}</th>
                            <th>{{ __( 'Metric' ) }}</th>
                            <th>{{ __( 'p75' ) }}</th>
                            <th>{{ __( 'Samples' ) }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pages as $row)
                            <tr wire:key="pages-{{ $loop->index }}">
                                <td>{{ $row['route'] }}</td>
                                <td>{{ $row['metric'] }}</td>
                                <td>{{ number_format( $row['p75'], 2 ) }}</td>
                                <td>{{ number_format( $row['sample_count'] ) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @elseif($activeTab === 'images')
            <h2 class="performance-dashboard__heading">{{ __( 'Images' ) }}</h2>
            <p class="performance-dashboard__empty">{{ __( 'Image optimization details appear here once the optimization queue has run.' ) }}</p>
        @elseif($activeTab === 'cache')
            <h2 class="performance-dashboard__heading">{{ __( 'Cache' ) }}</h2>

            <dl class="performance-dashboard__summary">
                <div>
                    <dt>{{ __( 'Page cache entries' ) }}</dt>
                    <dd>{{ number_format( $cacheSummary['page']['entries'] ) }}</dd>
                </div>
                <div>
                    <dt>{{ __( 'Fragment cache entries' ) }}</dt>
                    <dd>{{ number_format( $cacheSummary['fragment']['entries'] ) }}</dd>
                </div>
                <div>
                    <dt>{{ __( 'Fragment tags' ) }}</dt>
                    <dd>{{ number_format( $cacheSummary['fragment']['tags'] ) }}</dd>
                </div>
            </dl>

            @livewire('perf-cache-manager')
        @elseif($activeTab === 'queries')
            <h2 class="performance-dashboard__heading">{{ __( 'Slow Queries' ) }}</h2>

            @if(empty($queries))
                <p class="performance-dashboard__empty">{{ __( 'No slow queries logged for this range.' ) }}</p>
            @else
                <table class="performance-dashboard__table" data-testid="queries-table">
                    <thead>
                        <tr>
                            <th>{{ __( 'Query' ) }}</th>
                            <th>{{ __( 'Time (ms)' ) }}</th>
                            <th>{{ __( 'Route' ) }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($queries as $row)
                            <tr wire:key="query-{{ $loop->index }}">
                                <td><code>{{ \Illuminate\Support\Str::limit( $row['query'], 120 ) }}</code></td>
                                <td>{{ number_format( $row['time_ms'], 2 ) }}</td>
                                <td>{{ $row['route'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @elseif($activeTab === 'recommendations')
            <h2 class="performance-dashboard__heading">{{ __( 'Recommendations' ) }}</h2>

            @if(empty($recommendations))
                <p class="performance-dashboard__empty">{{ __( 'No recommendations at the moment — all tracked metrics are in the good band.' ) }}</p>
            @else
                <ul class="performance-dashboard__recommendations">
                    @foreach($recommendations as $item)
                        <li wire:key="rec-{{ $loop->index }}" @class([
                            'performance-dashboard__recommendation',
                            'is-high' => $item['severity'] === 'high',
                            'is-medium' => $item['severity'] === 'medium',
                        ])>
                            <strong>{{ $item['title'] }}</strong>
                            <p>{{ $item['body'] }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif
    </div>

    @isset($footer)
        <div class="performance-dashboard__footer">
            {{ $footer }}
        </div>
    @endisset
</div>
