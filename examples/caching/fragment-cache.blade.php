{{--
    Fragment caching.

    Wrap an expensive Blade fragment in @perfCache. The rendered HTML
    is stored in the fragment cache under the given key and tagged so
    it can be invalidated by tag from anywhere in the app.

    Requires:
      'features' => [
          'fragment_caching' => true,
      ],
--}}

{{-- Cache the header for 30 minutes; tag it so a nav change flushes it. --}}
@perfCache( 'site-header', ttl: 1800, tags: [ 'nav', 'header' ] )
    <header class="site-header">
        <x-nav :items="$navItems" />
    </header>
@endPerfCache

{{-- Per-user product recommendations — key includes the user id so
     different users see different cached HTML. --}}
@perfCache( "recs.user.{$user->id}", ttl: 900, tags: [ "user.{$user->id}", 'recommendations' ] )
    <section class="recommendations">
        @foreach ( $recommendations as $product )
            <x-product-card :product="$product" />
        @endforeach
    </section>
@endPerfCache

{{-- Flush by tag from a controller / listener:

     use ArtisanPackUI\Performance\Cache\CacheInvalidator;

     app( CacheInvalidator::class )->invalidateFragmentTag( 'nav' );
--}}
