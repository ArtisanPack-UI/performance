<?php

/**
 * Cache invalidation.
 *
 * Two patterns:
 *   1. Model observer — invalidate tags whenever a model is saved,
 *      updated, or deleted.
 *   2. Manual invalidation — call CacheInvalidator directly from a
 *      controller / listener / job.
 */

namespace App\Observers;

use App\Models\Product;
use ArtisanPackUI\Performance\Cache\CacheInvalidator;

class ProductObserver
{
    public function __construct(
        protected CacheInvalidator $invalidator,
    ) {}

    public function saved( Product $product ): void
    {
        // Flush the individual product's fragment cache.
        $this->invalidator->invalidateFragmentTag( "product.{$product->id}" );

        // Flush any product-list pages that showed this product.
        $this->invalidator->invalidateFragmentTag( 'product-list' );

        // Flush the page cache for the product URL and its parent category.
        $this->invalidator->invalidatePagePattern( "products/{$product->slug}" );
        $this->invalidator->invalidatePagePattern( "categories/{$product->category->slug}" );
    }

    public function deleted( Product $product ): void
    {
        $this->invalidator->invalidateFragmentTag( "product.{$product->id}" );
        $this->invalidator->invalidateFragmentTag( 'product-list' );
        $this->invalidator->flushPageCache();
    }
}

/*
 * Register the observer in App\Providers\AppServiceProvider::boot():
 *
 *   Product::observe(ProductObserver::class);
 *
 * Manual invalidation from anywhere:
 *
 *   use ArtisanPackUI\Performance\Cache\CacheInvalidator;
 *
 *   app(CacheInvalidator::class)->purgeAll();               // Everything.
 *   app(CacheInvalidator::class)->flushPageCache();          // All pages.
 *   app(CacheInvalidator::class)->invalidatePagePattern('products/*');
 *   app(CacheInvalidator::class)->invalidateFragmentTag('nav');
 */
