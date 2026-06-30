<?php

declare( strict_types=1 );

use ArtisanPackUI\Performance\Database\IndexSuggester;
use ArtisanPackUI\Performance\Models\SlowQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses( RefreshDatabase::class );

it( 'suggests indexes from WHERE clauses', function (): void {
    $suggester   = new IndexSuggester;
    $suggestions = $suggester->suggest( [
        'SELECT * FROM posts WHERE user_id = ?',
        'SELECT * FROM posts WHERE user_id = ?',
    ] );

    expect( $suggestions )->toHaveCount( 1 );
    expect( $suggestions[0]['table'] )->toBe( 'posts' );
    expect( $suggestions[0]['columns'] )->toBe( [ 'user_id' ] );
    expect( $suggestions[0]['occurrences'] )->toBe( 2 );
} );

it( 'composes WHERE + ORDER BY into a composite index suggestion', function (): void {
    $suggester   = new IndexSuggester;
    $suggestions = $suggester->suggest( [
        'SELECT * FROM posts WHERE user_id = ? ORDER BY created_at DESC',
    ] );

    expect( $suggestions[0]['columns'] )->toBe( [ 'user_id', 'created_at' ] );
    expect( $suggestions[0]['sources'] )->toContain( 'where+order_by' );
} );

it( 'extracts ORDER BY columns when no WHERE clause exists', function (): void {
    $suggester   = new IndexSuggester;
    $suggestions = $suggester->suggest( [
        'SELECT * FROM posts ORDER BY created_at DESC',
    ] );

    expect( $suggestions[0]['columns'] )->toBe( [ 'created_at' ] );
    expect( $suggestions[0]['sources'] )->toContain( 'order_by' );
} );

it( 'ranks suggestions by occurrence count', function (): void {
    $queries = [
        'SELECT * FROM posts WHERE user_id = ?',
        'SELECT * FROM posts WHERE user_id = ?',
        'SELECT * FROM posts WHERE user_id = ?',
        'SELECT * FROM comments WHERE post_id = ?',
    ];

    $suggestions = ( new IndexSuggester )->suggest( $queries );

    expect( $suggestions[0]['table'] )->toBe( 'posts' );
    expect( $suggestions[0]['occurrences'] )->toBe( 3 );
    expect( $suggestions[1]['table'] )->toBe( 'comments' );
    expect( $suggestions[1]['occurrences'] )->toBe( 1 );
} );

it( 'classifies impact based on occurrences', function (): void {
    $high = ( new IndexSuggester )->suggest( array_fill( 0, 6, 'SELECT * FROM posts WHERE user_id = ?' ) );
    expect( $high[0]['impact'] )->toBe( 'High' );

    $medium = ( new IndexSuggester )->suggest( array_fill( 0, 2, 'SELECT * FROM posts WHERE user_id = ?' ) );
    expect( $medium[0]['impact'] )->toBe( 'Medium' );

    $low = ( new IndexSuggester )->suggest( [ 'SELECT * FROM posts WHERE user_id = ?' ] );
    expect( $low[0]['impact'] )->toBe( 'Low' );
} );

it( 'captures JOIN candidates as additional suggestions', function (): void {
    $suggester   = new IndexSuggester;
    $suggestions = $suggester->suggest( [
        'SELECT * FROM posts JOIN users ON users.id = posts.user_id WHERE posts.published = ?',
    ] );

    $tables = array_column( $suggestions, 'table' );

    expect( $tables )->toContain( 'posts' );
    expect( $tables )->toContain( 'users' );
} );

it( 'returns an empty array when no SQL is supplied', function (): void {
    expect( ( new IndexSuggester )->suggest( [] ) )->toBe( [] );
} );

it( 'loads queries from the slow query log when no source is supplied', function (): void {
    SlowQuery::create( [
        'query'            => 'SELECT * FROM orders WHERE status = ? ORDER BY created_at DESC',
        'query_normalized' => 'select * from orders where status = ? order by created_at desc',
        'bindings'         => [ 'pending' ],
        'time_ms'          => 250.0,
        'connection'       => 'testbench',
        'file'             => null,
        'line'             => null,
        'trace'            => [],
        'route'            => null,
    ] );

    $suggestions = ( new IndexSuggester )->suggest();

    expect( $suggestions[0]['table'] )->toBe( 'orders' );
    expect( $suggestions[0]['columns'] )->toBe( [ 'status', 'created_at' ] );
} );

it( 'generates migration body with indexes grouped by table', function (): void {
    $suggester = new IndexSuggester;

    $body = $suggester->generateMigrationBody( [
        [ 'table' => 'posts', 'columns' => [ 'user_id', 'created_at' ] ],
        [ 'table' => 'comments', 'columns' => [ 'post_id' ] ],
    ] );

    expect( $body )->toContain( "Schema::table('posts'" );
    expect( $body )->toContain( "\$table->index(['user_id', 'created_at']);" );
    expect( $body )->toContain( "Schema::table('comments'" );
    expect( $body )->toContain( "\$table->index(['post_id']);" );
} );

it( 'returns an empty migration body when there are no suggestions', function (): void {
    expect( ( new IndexSuggester )->generateMigrationBody( [] ) )->toBe( '' );
} );

it( 'ignores comments and extra whitespace', function (): void {
    $queries = [
        "SELECT * FROM posts /* hot path */ WHERE user_id = ? -- traced\nORDER BY created_at DESC",
    ];

    $suggestions = ( new IndexSuggester )->suggest( $queries );

    expect( $suggestions[0]['table'] )->toBe( 'posts' );
    expect( $suggestions[0]['columns'] )->toBe( [ 'user_id', 'created_at' ] );
} );

it( 'exposes the suggester command and outputs the table', function (): void {
    SlowQuery::create( [
        'query'            => 'SELECT * FROM posts WHERE user_id = ? ORDER BY created_at DESC',
        'query_normalized' => 'select * from posts where user_id = ? order by created_at desc',
        'bindings'         => [ 1 ],
        'time_ms'          => 250.0,
        'connection'       => 'testbench',
        'file'             => null,
        'line'             => null,
        'trace'            => [],
        'route'            => null,
    ] );

    Illuminate\Support\Facades\Artisan::call( 'perf:suggest-indexes' );
    $output = Illuminate\Support\Facades\Artisan::output();

    expect( $output )->toContain( 'posts' );
    expect( $output )->toContain( 'user_id' );
    expect( $output )->toContain( 'created_at' );
} );

it( 'warns when no suggestions can be derived', function (): void {
    Illuminate\Support\Facades\Artisan::call( 'perf:suggest-indexes' );
    $output = Illuminate\Support\Facades\Artisan::output();

    expect( $output )->toContain( 'No index suggestions' );
} );

it( 'writes a migration file when --migration is passed', function (): void {
    SlowQuery::create( [
        'query'            => 'SELECT * FROM posts WHERE user_id = ?',
        'query_normalized' => 'select * from posts where user_id = ?',
        'bindings'         => [ 1 ],
        'time_ms'          => 250.0,
        'connection'       => 'testbench',
        'file'             => null,
        'line'             => null,
        'trace'            => [],
        'route'            => null,
    ] );

    // --path is restricted to paths inside the application base. Use a
    // unique subdirectory under base_path() so the test stays isolated.
    $relative  = 'tests/_tmp/index-suggester-' . uniqid( '', true );
    $directory = base_path( $relative );

    try {
        test()->artisan( 'perf:suggest-indexes', [ '--migration' => true, '--path' => $directory ] )
            ->assertSuccessful();

        $files = glob( $directory . DIRECTORY_SEPARATOR . '*_perf_suggested_indexes.php' );

        expect( $files )->toHaveCount( 1 );

        $contents = (string) file_get_contents( $files[0] );
        expect( $contents )->toContain( "Schema::table('posts'" );
        expect( $contents )->toContain( "\$table->index(['user_id']);" );
    } finally {
        // Cleanup runs even if the assertions above throw, so a failed
        // run doesn't leave orphan dirs in the repo.
        foreach ( (array) glob( $directory . DIRECTORY_SEPARATOR . '*' ) as $file ) {
            @unlink( (string) $file );
        }
        @rmdir( $directory );
        @rmdir( dirname( $directory ) );
    }
} );

it( 'rejects --path values that resolve outside the application base path', function (): void {
    SlowQuery::create( [
        'query'            => 'SELECT * FROM posts WHERE user_id = ?',
        'query_normalized' => 'select * from posts where user_id = ?',
        'bindings'         => [ 1 ],
        'time_ms'          => 250.0,
        'connection'       => 'testbench',
        'file'             => null,
        'line'             => null,
        'trace'            => [],
        'route'            => null,
    ] );

    $escapePath = '/tmp/perf-index-suggester-' . uniqid( '', true );

    test()->artisan( 'perf:suggest-indexes', [ '--migration' => true, '--path' => $escapePath ] )
        ->assertFailed();

    expect( file_exists( $escapePath ) )->toBeFalse();
} );

it( 'gives each migration a unique filename even when invoked back-to-back', function (): void {
    SlowQuery::create( [
        'query'            => 'SELECT * FROM posts WHERE user_id = ?',
        'query_normalized' => 'select * from posts where user_id = ?',
        'bindings'         => [ 1 ],
        'time_ms'          => 250.0,
        'connection'       => 'testbench',
        'file'             => null,
        'line'             => null,
        'trace'            => [],
        'route'            => null,
    ] );

    $directory = base_path( 'tests/_tmp/index-suggester-collision-' . uniqid( '', true ) );

    try {
        test()->artisan( 'perf:suggest-indexes', [ '--migration' => true, '--path' => $directory ] )
            ->assertSuccessful();
        test()->artisan( 'perf:suggest-indexes', [ '--migration' => true, '--path' => $directory ] )
            ->assertSuccessful();

        $files = (array) glob( $directory . DIRECTORY_SEPARATOR . '*_perf_suggested_indexes.php' );

        expect( $files )->toHaveCount( 2 );
    } finally {
        foreach ( (array) glob( $directory . DIRECTORY_SEPARATOR . '*' ) as $file ) {
            @unlink( (string) $file );
        }
        @rmdir( $directory );
        @rmdir( dirname( $directory ) );
    }
} );
