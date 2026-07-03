<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Support\MediaOptimizationStatus;
use ArtisanPackUI\Performance\Traits\HasOptimizedMedia;
use Tests\Fixtures\MediaModelStub;

it( 'returns pending status when the column is empty', function (): void {
    $media = new MediaModelStub;

    expect( $media->getOptimizationStatus() )->toBe( MediaOptimizationStatus::PENDING )
        ->and( $media->isOptimized() )->toBeFalse();
} );

it( 'reports optimized only when the status column is completed', function (): void {
    $media                      = new MediaModelStub;
    $media->optimization_status = MediaOptimizationStatus::COMPLETED;

    expect( $media->isOptimized() )->toBeTrue()
        ->and( $media->getOptimizationStatus() )->toBe( 'completed' );

    $media->optimization_status = MediaOptimizationStatus::PROCESSING;

    expect( $media->isOptimized() )->toBeFalse()
        ->and( $media->getOptimizationStatus() )->toBe( 'processing' );
} );

it( 'returns the dominant color when it is set, null when not', function (): void {
    $media                 = new MediaModelStub;
    $media->dominant_color = '#3b82f6';

    expect( $media->getDominantColor() )->toBe( '#3b82f6' );

    $media->dominant_color = null;
    expect( $media->getDominantColor() )->toBeNull();

    $media->dominant_color = '';
    expect( $media->getDominantColor() )->toBeNull();
} );

it( 'resolves a format-only URL from the optimized_formats map', function (): void {
    $media                    = new MediaModelStub;
    $media->optimized_formats = [
        'webp' => '/storage/media/1/optimized/image.webp',
    ];

    expect( $media->getOptimizedUrl( 'webp' ) )->toBe( '/storage/media/1/optimized/image.webp' )
        ->and( $media->getOptimizedUrl( 'avif' ) )->toBeNull();
} );

it( 'resolves a format+size URL from the nested map', function (): void {
    $media                    = new MediaModelStub;
    $media->optimized_formats = [
        'webp' => [
            '320' => '/storage/media/1/optimized/image-320.webp',
            '640' => '/storage/media/1/optimized/image-640.webp',
        ],
    ];

    expect( $media->getOptimizedUrl( 'webp', 320 ) )->toBe( '/storage/media/1/optimized/image-320.webp' )
        ->and( $media->getOptimizedUrl( 'webp', '640' ) )->toBe( '/storage/media/1/optimized/image-640.webp' )
        ->and( $media->getOptimizedUrl( 'webp', 1024 ) )->toBeNull();
} );

it( 'resolves a size-only URL from the optimized_sizes map', function (): void {
    $media                  = new MediaModelStub;
    $media->optimized_sizes = [
        '320' => '/storage/media/1/optimized/image-320.jpg',
        '640' => '/storage/media/1/optimized/image-640.jpg',
    ];

    expect( $media->getOptimizedUrl( null, 320 ) )->toBe( '/storage/media/1/optimized/image-320.jpg' )
        ->and( $media->getOptimizedUrl( null, 999 ) )->toBeNull();
} );

it( 'builds a srcset string from the optimized_sizes map', function (): void {
    $media                  = new MediaModelStub;
    $media->optimized_sizes = [
        '320' => '/storage/media/1/optimized/image-320.jpg',
        '640' => '/storage/media/1/optimized/image-640.jpg',
    ];

    expect( $media->getSrcset() )
        ->toBe( '/storage/media/1/optimized/image-320.jpg 320w, /storage/media/1/optimized/image-640.jpg 640w' );
} );

it( 'builds a per-format srcset when the format map holds nested sizes', function (): void {
    $media                    = new MediaModelStub;
    $media->optimized_formats = [
        'webp' => [
            '320' => '/storage/media/1/optimized/image-320.webp',
            '640' => '/storage/media/1/optimized/image-640.webp',
        ],
    ];

    expect( $media->getSrcset( 'webp' ) )
        ->toBe( '/storage/media/1/optimized/image-320.webp 320w, /storage/media/1/optimized/image-640.webp 640w' );
} );

it( 'returns an empty srcset when the columns are empty', function (): void {
    $media = new MediaModelStub;

    expect( $media->getSrcset() )->toBe( '' )
        ->and( $media->getSrcset( 'webp' ) )->toBe( '' );
} );

it( 'skips entries with non-numeric width keys defensively', function (): void {
    $media                  = new MediaModelStub;
    $media->optimized_sizes = [
        '320'  => '/storage/media/1/optimized/image-320.jpg',
        'name' => '/storage/media/1/should-be-skipped.jpg',
    ];

    // Non-numeric keys are skipped so the descriptor stays well-formed.
    expect( $media->getSrcset() )->toBe( '/storage/media/1/optimized/image-320.jpg 320w' );
} );

it( 'normalizes a raw JSON string returned by the getAttribute call', function (): void {
    // Stub model without any casts — mirrors an application that opted out of
    // registering the trait-managed columns as `array` casts. The trait must
    // still decode the JSON.
    $media = new class extends Illuminate\Database\Eloquent\Model {
        use HasOptimizedMedia;

        protected $guarded = [];
    };

    $media->setRawAttributes( [
        'optimized_sizes' => json_encode( [
            '320' => '/storage/media/1/optimized/image-320.jpg',
        ] ),
    ] );

    expect( $media->getSrcset() )->toBe( '/storage/media/1/optimized/image-320.jpg 320w' );
} );
