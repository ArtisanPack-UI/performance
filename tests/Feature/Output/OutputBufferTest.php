<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Output\OutputBuffer;

beforeEach( function (): void {
    $this->bufferBaseline = ob_get_level();
} );

afterEach( function (): void {
    // Restore the exact buffer depth we entered the test with. PHPUnit
    // flags tests as "risky" if `ob_get_level()` shifts during the
    // test, so a test that leaks an open buffer or accidentally closes
    // PHPUnit's own buffer would otherwise contaminate every test
    // after it.
    while ( ob_get_level() > $this->bufferBaseline ) {
        @ob_end_clean();
    }
} );

it( 'returns the buffer contents while the buffer is open', function (): void {
    $buffer = new OutputBuffer;

    $buffer->start();
    echo 'captured';

    expect( $buffer->get() )->toBe( 'captured' );
    expect( $buffer->isActive() )->toBeTrue();

    $buffer->end();
} );

it( 'returns the contents and closes the buffer on end', function (): void {
    $baseline = ob_get_level();
    $buffer   = new OutputBuffer;

    $buffer->start();
    echo 'payload';

    $contents = $buffer->end();

    expect( $contents )->toBe( 'payload' );
    expect( $buffer->isActive() )->toBeFalse();
    expect( ob_get_level() )->toBe( $baseline );
} );

it( 'reports zero depth and empty contents when no buffer is active', function (): void {
    $buffer = new OutputBuffer;

    expect( $buffer->isActive() )->toBeFalse();
    expect( $buffer->depth() )->toBe( 0 );
    expect( $buffer->get() )->toBe( '' );
    expect( $buffer->end() )->toBe( '' );
} );

it( 'tracks depth across nested start and end calls', function (): void {
    $buffer = new OutputBuffer;

    $buffer->start();
    echo 'outer ';

    $buffer->start();
    echo 'inner';

    expect( $buffer->depth() )->toBe( 2 );
    expect( $buffer->get() )->toBe( 'inner' );

    expect( $buffer->end() )->toBe( 'inner' );
    expect( $buffer->depth() )->toBe( 1 );

    expect( $buffer->end() )->toBe( 'outer ' );
    expect( $buffer->depth() )->toBe( 0 );
} );

it( 'applies transformations in sequence', function (): void {
    $buffer = new OutputBuffer;

    $result = $buffer->transform( 'hello', [
        static fn ( string $content ): string => $content . ' world',
        static fn ( string $content ): string => strtoupper( $content ),
    ] );

    expect( $result )->toBe( 'HELLO WORLD' );
} );

it( 'skips non-string transformer return values without poisoning the chain', function (): void {
    $buffer = new OutputBuffer;

    $result = $buffer->transform( 'start', [
        static fn ( string $content ): string => $content . '-one',
        // Intentionally returns null — the chain should swallow this
        // and pass the previous string forward.
        static fn ( string $content ) => null,
        static fn ( string $content ): string => $content . '-two',
    ] );

    expect( $result )->toBe( 'start-one-two' );
} );

it( 'captures a producer and runs the transformer pipeline against the output', function (): void {
    $buffer = new OutputBuffer;

    $captured = $buffer->capture(
        static function (): void {
            echo '   captured   ';
        },
        [
            static fn ( string $content ): string => trim( $content ),
            static fn ( string $content ): string => strtoupper( $content ),
        ],
    );

    expect( $captured )->toBe( 'CAPTURED' );
    expect( $buffer->isActive() )->toBeFalse();
} );

it( 'closes the buffer even when the producer throws', function (): void {
    $baseline = ob_get_level();
    $buffer   = new OutputBuffer;

    $thrown = false;

    try {
        $buffer->capture( static function (): void {
            echo 'partial';

            throw new RuntimeException( 'producer exploded' );
        } );
    } catch ( RuntimeException $exception ) {
        $thrown = true;
    }

    expect( $thrown )->toBeTrue();
    expect( $buffer->isActive() )->toBeFalse();
    expect( ob_get_level() )->toBe( $baseline );
} );

it( 'reset closes every buffer this instance still owns', function (): void {
    $baseline = ob_get_level();
    $buffer   = new OutputBuffer;

    $buffer->start();
    $buffer->start();
    $buffer->start();

    expect( $buffer->depth() )->toBe( 3 );

    $buffer->reset();

    expect( $buffer->depth() )->toBe( 0 );
    expect( $buffer->isActive() )->toBeFalse();
    expect( ob_get_level() )->toBe( $baseline );
} );

it( 'ignores end calls when no buffer is open', function (): void {
    $baseline = ob_get_level();
    $buffer   = new OutputBuffer;

    expect( $buffer->end() )->toBe( '' );
    expect( $buffer->depth() )->toBe( 0 );
    expect( ob_get_level() )->toBe( $baseline );
} );
