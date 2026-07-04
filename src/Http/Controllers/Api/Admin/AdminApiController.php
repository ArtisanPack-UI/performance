<?php

/**
 * Base controller for the Performance admin JSON API.
 *
 * All admin endpoints hang off this to share a single gate check. The gate
 * defaults to `artisanpack.performance.dashboard.gate` and falls back to
 * `view-performance-dashboard` when the config is empty so a blanked-out
 * key cannot inadvertently expose admin data.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Http\Controllers\Api\Admin;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

/**
 * Base admin API controller.
 *
 *
 * @since      1.0.0
 */
abstract class AdminApiController extends Controller
{
    /**
     * Runs the configured admin gate. Kept protected so tests can subclass and mock.
     *
     * @since 1.0.0
     */
    protected function authorizeAdmin(): void
    {
        $gate = (string) config( 'artisanpack.performance.dashboard.gate', 'view-performance-dashboard' );

        if ( '' === $gate ) {
            $gate = 'view-performance-dashboard';
        }

        Gate::authorize( $gate );
    }
}
