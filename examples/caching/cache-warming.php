<?php

/**
 * Cache warming.
 *
 * `perf:warm-cache` walks a list of URLs and requests each one so the
 * page cache and fragment cache are populated before the first real
 * visitor arrives. Combine with the deploy hook to avoid a
 * cold-cache latency spike after every deploy.
 */

use Illuminate\Console\Scheduling\Schedule;

// ---------------------------------------------------------------------
// 1. Configure the URLs to warm.
// ---------------------------------------------------------------------
//
// config/artisanpack/performance.php
//
//   'cache_warming' => [
//       'enabled'     => true,
//       'concurrency' => 4,
//       'urls' => [
//           '/',
//           '/products',
//           '/products/featured',
//           '/blog',
//           '/pricing',
//       ],
//       'sitemap' => 'https://example.com/sitemap.xml',
//   ],

// ---------------------------------------------------------------------
// 2. Schedule the warm command.
// ---------------------------------------------------------------------
// routes/console.php (Laravel 12)

/** @var Schedule $schedule */

// Warm every 15 minutes so URLs never fall out of cache during peak.
$schedule->command( 'perf:warm-cache' )
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Warm the sitemap-derived list once an hour.
$schedule->command( 'perf:warm-cache', [ '--sitemap' => 'https://example.com/sitemap.xml' ] )
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

// ---------------------------------------------------------------------
// 3. Warm on deploy.
// ---------------------------------------------------------------------
//
//   # deploy.sh
//   php artisan config:cache
//   php artisan route:cache
//   php artisan perf:warm-cache --force
