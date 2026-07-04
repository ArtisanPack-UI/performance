<?php

/**
 * Custom metrics.
 *
 * The Performance facade exposes `record()` for arbitrary metrics.
 * Recorded metrics land in the `performance_metrics` table alongside
 * Core Web Vitals so the aggregator, recommendations engine, and
 * dashboard can render them.
 */

namespace App\Http\Controllers;

use ArtisanPackUI\Performance\Facades\Performance;
use Illuminate\Http\Request;

class CheckoutController
{
    public function complete( Request $request ): array
    {
        $start = microtime( true );

        $order = $this->processOrder( $request );

        $elapsedMs = (int) ( ( microtime( true ) - $start ) * 1000 );

        // Record a domain-specific metric.
        Performance::record( 'checkout.duration', $elapsedMs, [
            'route'      => $request->route()->getName(),
            'items'      => $order->items->count(),
            'total'      => $order->total,
            'gateway'    => $order->gateway,
        ] );

        return [ 'id' => $order->id ];
    }

    protected function processOrder( Request $request ): object
    {
        // Domain logic...
        return (object) [
            'id'      => 1,
            'items'   => collect(),
            'total'   => 0,
            'gateway' => 'stripe',
        ];
    }
}

/*
 * Query the aggregated metric from Tinker / a report:
 *
 *   use ArtisanPackUI\Performance\Models\PerformanceMetric;
 *
 *   PerformanceMetric::where('name', 'checkout.duration')
 *       ->where('created_at', '>=', now()->subDay())
 *       ->selectRaw('avg(value) as avg_ms, percentile_cont(0.95) within group (order by value) as p95')
 *       ->first();
 *
 * Or from the admin API — pass the metric name to the chart endpoint:
 *
 *   GET /api/performance/admin/chart?metrics[]=checkout.duration&range=24h
 */
