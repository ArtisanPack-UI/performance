<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Models\SlowQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses( RefreshDatabase::class );

beforeEach( function (): void {
    // Migration writes require --path to resolve inside the application
    // base path, so anchor the temporary dir there rather than under
    // sys_get_temp_dir() (which the base-path guard rejects).
    $this->migrationRoot = base_path( 'perf-suggest-indexes-' . uniqid() );
    File::ensureDirectoryExists( $this->migrationRoot );
} );

afterEach( function (): void {
    if ( is_dir( $this->migrationRoot ) ) {
        File::deleteDirectory( $this->migrationRoot );
    }
} );

it( 'warns when no slow queries have been captured', function (): void {
    $this->artisan( 'perf:suggest-indexes' )
        ->expectsOutputToContain( 'No index suggestions' )
        ->assertSuccessful();
} );

it( 'succeeds when slow queries have been captured', function (): void {
    SlowQuery::create( [
        'query'            => 'SELECT * FROM posts WHERE user_id = ?',
        'query_normalized' => 'SELECT * FROM posts WHERE user_id = ?',
        'bindings'         => [],
        'time_ms'          => 250,
        'connection'       => 'testbench',
    ] );

    // Console table output normalizes whitespace between borders/padding
    // so we assert on exit status rather than specific cell contents;
    // the underlying suggestion logic is exhaustively covered by
    // `tests/Feature/Database/IndexSuggesterTest.php`.
    $this->artisan( 'perf:suggest-indexes' )
        ->assertSuccessful();
} );

it( 'writes a migration file when --migration is passed', function (): void {
    SlowQuery::create( [
        'query'            => 'SELECT * FROM posts WHERE user_id = ?',
        'query_normalized' => 'SELECT * FROM posts WHERE user_id = ?',
        'bindings'         => [],
        'time_ms'          => 250,
        'connection'       => 'testbench',
    ] );

    $this->artisan( 'perf:suggest-indexes', [
        '--migration' => true,
        '--path'      => $this->migrationRoot,
    ] )->assertSuccessful();

    $files = File::files( $this->migrationRoot );

    expect( $files )->not->toBeEmpty();
    expect( File::get( $files[0]->getPathname() ) )
        ->toContain( 'Schema::table' )
        ->toContain( '$table->index' );
} );

it( 'fails when --path escapes the application base path', function (): void {
    $outside = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'outside-app-' . uniqid();
    File::ensureDirectoryExists( $outside );

    SlowQuery::create( [
        'query'            => 'SELECT * FROM posts WHERE user_id = ?',
        'query_normalized' => 'SELECT * FROM posts WHERE user_id = ?',
        'bindings'         => [],
        'time_ms'          => 250,
        'connection'       => 'testbench',
    ] );

    $this->artisan( 'perf:suggest-indexes', [
        '--migration' => true,
        '--path'      => $outside,
    ] )->assertFailed();

    File::deleteDirectory( $outside );
} );
