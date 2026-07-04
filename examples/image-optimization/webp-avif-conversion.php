<?php

/**
 * WebP + AVIF conversion example.
 *
 * Two paths:
 *   1. Bulk: convert every image in the media library (or a specific
 *      disk) via the shipped Artisan command.
 *   2. On upload: dispatch the queued job so newly uploaded images are
 *      converted without blocking the request.
 */

// ---------------------------------------------------------------------
// 1. Bulk convert existing images from the CLI.
// ---------------------------------------------------------------------

// Convert everything under storage/app/public/uploads to WebP + AVIF.
//
//   php artisan perf:generate-webp --disk=public --path=uploads --format=webp
//   php artisan perf:generate-webp --disk=public --path=uploads --format=avif
//
// The command chunks by directory and queues per-image conversions
// through the `images` queue defined in
// artisanpack.performance.image_optimization.queue.

// ---------------------------------------------------------------------
// 2. Convert on upload.
// ---------------------------------------------------------------------

use ArtisanPackUI\Performance\Jobs\OptimizeImageJob;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;

Route::post( '/upload', function ( Request $request ): array {
    /** @var UploadedFile $file */
    $file = $request->file( 'image' );
    $path = $file->store( 'uploads', 'public' );

    // Queue conversion so the response returns immediately.
    OptimizeImageJob::dispatch( 'public', $path )
        ->onQueue( config( 'artisanpack.performance.image_optimization.queue', 'default' ) );

    return [
        'path' => $path,
    ];
} );
