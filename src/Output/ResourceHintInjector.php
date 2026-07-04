<?php

/**
 * Resource hint injector.
 *
 * Central registry for browser resource hints. Combines hints from
 * three sources — package configuration
 * (`artisanpack.performance.resource_hints.*`), manually-registered
 * hints (via the fluent setters or `addHint()`), and pluggable
 * `ResourceHintProvider` instances — deduplicates them by
 * `(rel, href, as)`, and renders them as either an HTML `<link>` block
 * for the document head or a list of RFC 8288 `Link` header values for
 * HTTP/2 push.
 *
 * The injector is registered as a container singleton so manual
 * additions (e.g. a controller calling `Performance::resourceHints()
 * ->preload(...)` mid-request) and the middleware that drains the
 * registry on response share the same instance.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Output;

use ArtisanPackUI\Performance\Contracts\ResourceHintProvider;

/**
 * Resource hint injector.
 *
 *
 * @since      1.0.0
 */
class ResourceHintInjector
{
    /**
     * Manually-registered hints in insertion order.
     *
     * @since 1.0.0
     *
     * @var array<int, ResourceHint>
     */
    protected array $manual = [];

    /**
     * Registered providers consulted at render time.
     *
     * @since 1.0.0
     *
     * @var array<int, ResourceHintProvider>
     */
    protected array $providers = [];

    /**
     * Auto-detected hints captured from rendered HTML.
     *
     * Stored separately from `$manual` so callers (typically the
     * `InjectResourceHints` middleware) can reset just the auto-detected
     * pool between requests without disturbing manual registrations.
     *
     * @since 1.0.0
     *
     * @var array<int, ResourceHint>
     */
    protected array $autoDetected = [];

    /**
     * Registers a preconnect hint.
     *
     * @since 1.0.0
     *
     * @param  string  $href  Origin to preconnect to.
     * @param  string|null  $crossorigin  Optional crossorigin attribute (`anonymous`, `use-credentials`, empty for bare).
     *
     * @return $this
     */
    public function preconnect( string $href, ?string $crossorigin = null ): self
    {
        return $this->addHint( new ResourceHint(
            rel: 'preconnect',
            href: $href,
            crossorigin: $crossorigin,
        ) );
    }

    /**
     * Registers a DNS prefetch hint.
     *
     * @since 1.0.0
     *
     * @param  string  $href  Origin to resolve.
     *
     * @return $this
     */
    public function dnsPrefetch( string $href ): self
    {
        return $this->addHint( new ResourceHint( rel: 'dns-prefetch', href: $href ) );
    }

    /**
     * Registers a preload hint.
     *
     * @since 1.0.0
     *
     * @param  string  $href  Resource URL to preload.
     * @param  string|null  $as  Preload type (`font`, `script`, `style`, …).
     * @param  string|null  $type  MIME type (e.g. `font/woff2`).
     * @param  string|null  $crossorigin  Crossorigin attribute (required for fonts).
     * @param  string|null  $fetchpriority  Optional fetchpriority hint.
     *
     * @return $this
     */
    public function preload(
        string $href,
        ?string $as = null,
        ?string $type = null,
        ?string $crossorigin = null,
        ?string $fetchpriority = null,
    ): self {
        return $this->addHint( new ResourceHint(
            rel: 'preload',
            href: $href,
            as: $as,
            type: $type,
            crossorigin: $crossorigin,
            fetchpriority: $fetchpriority,
        ) );
    }

    /**
     * Registers a prefetch hint.
     *
     * @since 1.0.0
     *
     * @param  string  $href  Resource URL to prefetch.
     * @param  string|null  $as  Optional as hint for the prefetched resource.
     *
     * @return $this
     */
    public function prefetch( string $href, ?string $as = null ): self
    {
        return $this->addHint( new ResourceHint(
            rel: 'prefetch',
            href: $href,
            as: $as,
        ) );
    }

    /**
     * Registers a pre-built hint.
     *
     * Appended to the manual pool; subsequent `all()` calls deduplicate
     * against config-driven hints automatically.
     *
     * @since 1.0.0
     *
     * @param  ResourceHint  $hint  The hint to record.
     *
     * @return $this
     */
    public function addHint( ResourceHint $hint ): self
    {
        $this->manual[] = $hint;

        return $this;
    }

    /**
     * Registers a hint provider consulted at render time.
     *
     * @since 1.0.0
     *
     * @param  ResourceHintProvider  $provider  Provider instance.
     *
     * @return $this
     */
    public function registerProvider( ResourceHintProvider $provider ): self
    {
        $this->providers[] = $provider;

        return $this;
    }

    /**
     * Records an auto-detected hint discovered from rendered HTML.
     *
     * @since 1.0.0
     *
     * @param  ResourceHint  $hint  The hint to record.
     *
     * @return $this
     */
    public function addAutoDetected( ResourceHint $hint ): self
    {
        $this->autoDetected[] = $hint;

        return $this;
    }

    /**
     * Returns every resolved hint after deduplication and (optional) filtering.
     *
     * Hint ordering preserves the source ordering: config first
     * (preconnect → dns-prefetch → preload → prefetch), then manual
     * registrations in call order, then provider hints in registration
     * order, then auto-detected hints. Within each source the first
     * occurrence wins on collision.
     *
     * @since 1.0.0
     *
     * @param  array<int, string>|null  $rels  Optional filter — only return hints whose `rel` is in the list.
     *
     * @return array<int, ResourceHint>
     */
    public function all( ?array $rels = null ): array
    {
        $seen   = [];
        $result = [];

        foreach ( $this->collect() as $hint ) {
            $key = $hint->dedupKey();

            if ( isset( $seen[ $key ] ) ) {
                continue;
            }

            if ( null !== $rels && ! in_array( $hint->rel, $rels, true ) ) {
                continue;
            }

            $seen[ $key ] = true;
            $result[]     = $hint;
        }

        return $result;
    }

    /**
     * Renders every resolved hint as a newline-joined HTML block.
     *
     * @since 1.0.0
     *
     * @param  array<int, string>|null  $rels  Optional rel filter.
     */
    public function render( ?array $rels = null ): string
    {
        $html = [];

        foreach ( $this->all( $rels ) as $hint ) {
            $html[] = $hint->toLinkElement();
        }

        return implode( "\n", $html );
    }

    /**
     * Returns every resolved hint serialized as `Link` header values.
     *
     * Returns one value per hint; callers join with `, ` to form the
     * combined header value, or call `Response::header('Link', ...,
     * replace: false)` once per entry.
     *
     * @since 1.0.0
     *
     * @param  array<int, string>|null  $rels  Optional rel filter.
     *
     * @return array<int, string>
     */
    public function toLinkHeaders( ?array $rels = null ): array
    {
        $values = [];

        foreach ( $this->all( $rels ) as $hint ) {
            if ( ! $hint->isSafeForLinkHeader() ) {
                continue;
            }

            $values[] = $hint->toLinkHeader();
        }

        return $values;
    }

    /**
     * Clears manually-registered and auto-detected hints.
     *
     * Providers remain registered so the next request that resolves the
     * singleton still consults them. Use `clearProviders()` to drop
     * those too.
     *
     * @since 1.0.0
     *
     * @return $this
     */
    public function clear(): self
    {
        $this->manual       = [];
        $this->autoDetected = [];

        return $this;
    }

    /**
     * Drops the auto-detected pool while preserving manual registrations.
     *
     * Called between requests by the injection middleware so auto-detected
     * hints from request N don't leak into request N+1's response.
     *
     * @since 1.0.0
     *
     * @return $this
     */
    public function clearAutoDetected(): self
    {
        $this->autoDetected = [];

        return $this;
    }

    /**
     * Drops all registered providers.
     *
     * @since 1.0.0
     *
     * @return $this
     */
    public function clearProviders(): self
    {
        $this->providers = [];

        return $this;
    }

    /**
     * Reports whether any hints would be rendered.
     *
     * @since 1.0.0
     */
    public function hasHints(): bool
    {
        return ! empty( $this->all() );
    }

    /**
     * Collects every hint from every source in source-ordering.
     *
     * @since 1.0.0
     *
     * @return iterable<int, ResourceHint>
     */
    protected function collect(): iterable
    {
        yield from $this->configHints();
        yield from $this->manual;

        foreach ( $this->providers as $provider ) {
            foreach ( $provider->hints() as $hint ) {
                if ( $hint instanceof ResourceHint ) {
                    yield $hint;
                }
            }
        }

        yield from $this->autoDetected;
    }

    /**
     * Returns hints derived from package configuration.
     *
     * Reads `artisanpack.performance.resource_hints.{preconnect,
     * dns_prefetch, preload, prefetch}`. Each list accepts either a
     * bare string or a verbose associative entry — see
     * `ResourceHint::fromConfigEntry()` for the supported shapes.
     *
     * @since 1.0.0
     *
     * @return iterable<int, ResourceHint>
     */
    protected function configHints(): iterable
    {
        $relMap = [
            'preconnect'   => 'preconnect',
            'dns_prefetch' => 'dns-prefetch',
            'preload'      => 'preload',
            'prefetch'     => 'prefetch',
        ];

        foreach ( $relMap as $configKey => $rel ) {
            $entries = (array) config( "artisanpack.performance.resource_hints.{$configKey}", [] );

            foreach ( $entries as $entry ) {
                if ( ! is_string( $entry ) && ! is_array( $entry ) ) {
                    continue;
                }

                $hint = ResourceHint::fromConfigEntry( $rel, $entry );

                if ( null !== $hint ) {
                    yield $hint;
                }
            }
        }
    }
}
