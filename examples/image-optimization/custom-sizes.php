<?php

/**
 * Registering custom image sizes.
 *
 * Register once in a service provider; the size is then available to
 * every Blade component, the queued conversion pipeline, and the
 * `perf:generate-webp` command.
 */

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ImageSizeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Register at boot so the size is picked up before views render.
        config( [
            'artisanpack.performance.image_optimization.sizes.product-thumb' => [ 300, 300 ],
            'artisanpack.performance.image_optimization.sizes.hero'          => [ 1920, 1080 ],
            'artisanpack.performance.image_optimization.sizes.og'            => [ 1200, 630 ],
        ] );
    }
}

/*
 * Use the registered size in a Blade view:
 *
 *   <x-perf-responsive-image
 *       :src="asset('uploads/mug.jpg')"
 *       alt="Ceramic mug"
 *       size="product-thumb"
 *   />
 *
 * Or via the shipped helper function:
 *
 *   {{ perfImageUrl('uploads/mug.jpg', 'product-thumb') }}
 *
 * The queued conversion pipeline picks up new sizes on the next
 * `perf:generate-webp` run.
 */
