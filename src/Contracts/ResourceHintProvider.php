<?php

/**
 * Resource hint provider contract.
 *
 * Classes implementing this contract supply request-scoped resource
 * hints to the `ResourceHintInjector`. The injector resolves every
 * registered provider when assembling the document `<head>` block, so
 * implementations can derive hints from arbitrary application state
 * (the active locale, A/B-test bucket, feature flags, rendered HTML
 * scrape) instead of static configuration.
 *
 * Returning an empty array is valid — a provider may legitimately have
 * nothing to contribute for the current request.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Contracts;

use ArtisanPackUI\Performance\Output\ResourceHint;

/**
 * Resource hint provider contract.
 *
 *
 * @since      1.0.0
 */
interface ResourceHintProvider
{
    /**
     * Returns the hints this provider wants injected into the response.
     *
     * Implementations should return an array of fully-constructed
     * `ResourceHint` instances. Invalid hints should be filtered out by
     * the provider rather than handed to the injector to fail-soft on,
     * since the injector deduplicates and renders without re-validating.
     *
     * @since 1.0.0
     *
     * @return array<int, ResourceHint>
     */
    public function hints(): array;
}
