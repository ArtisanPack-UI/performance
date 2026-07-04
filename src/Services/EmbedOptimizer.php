<?php

/**
 * Embed optimizer service.
 *
 * Generates lightweight facade markup for third-party embeds (YouTube,
 * Vimeo, Twitter/X) that defer loading the heavy provider iframe until the
 * user interacts with the placeholder. Each embed type validates its own
 * ID format so untrusted callers can't smuggle arbitrary URLs into the
 * generated `<iframe>` or facade `<img>` source.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Services;

use InvalidArgumentException;

/**
 * Embed optimizer service.
 *
 *
 * @since      1.0.0
 */
class EmbedOptimizer
{
    /**
     * Supported embed providers.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const SUPPORTED_PROVIDERS = ['youtube', 'vimeo', 'twitter'];

    /**
     * Provider IDs aliased to their canonical name.
     *
     * `x` is mapped to `twitter` to match the platform rename without
     * forcing callers to pick one name.
     *
     * @since 1.0.0
     *
     * @var array<string, string>
     */
    protected const PROVIDER_ALIASES = [
        'x'     => 'twitter',
        'yt'    => 'youtube',
        'youtu' => 'youtube',
    ];

    /**
     * Reports whether the package can render facades for the given provider.
     *
     * @since 1.0.0
     *
     * @param  string  $provider  Caller-supplied provider name.
     */
    public function supports( string $provider ): bool
    {
        return in_array( $this->canonicalProvider( $provider ), self::SUPPORTED_PROVIDERS, true );
    }

    /**
     * Returns the canonical provider name, applying aliases.
     *
     * @since 1.0.0
     *
     * @param  string  $provider  Caller-supplied provider name.
     */
    public function canonicalProvider( string $provider ): string
    {
        $normalized = strtolower( trim( $provider ) );

        return self::PROVIDER_ALIASES[ $normalized ] ?? $normalized;
    }

    /**
     * Builds the facade descriptor for the given embed.
     *
     * The descriptor contains the canonical provider, validated ID, the
     * thumbnail URL the facade renders, the iframe URL the JS module swaps
     * in on click, and a localized aria label. Unsupported providers and
     * malformed IDs throw so the caller (typically the Blade component)
     * sees the error rather than silently emitting broken markup.
     *
     * @since 1.0.0
     *
     * @param  string  $provider  Provider name.
     * @param  string  $id  Provider-specific identifier.
     * @param  array<string,mixed>  $options  Optional overrides (title, params, thumbnail).
     *
     * @throws InvalidArgumentException When the provider is unsupported or the ID is malformed.
     *
     * @return array{provider: string, id: string, mode: string, thumbnail: string, iframe_url: string, embed_html: string, widgets_script: string, title: string, params: array<string, string>}
     */
    public function facade( string $provider, string $id, array $options = [] ): array
    {
        $canonical = $this->canonicalProvider( $provider );

        if ( ! in_array( $canonical, self::SUPPORTED_PROVIDERS, true ) ) {
            throw new InvalidArgumentException( sprintf(
                'Unsupported embed provider [%s]. Supported providers: %s.',
                $provider,
                implode( ', ', self::SUPPORTED_PROVIDERS ),
            ) );
        }

        if ( ! $this->isValidId( $canonical, $id ) ) {
            throw new InvalidArgumentException( sprintf(
                'Invalid %s embed id [%s].',
                $canonical,
                $id,
            ) );
        }

        $params    = $this->normalizeParams( $options['params'] ?? [] );
        $title     = $this->resolveTitle( $canonical, $options['title'] ?? null );
        $thumbnail = isset( $options['thumbnail'] ) && is_string( $options['thumbnail'] ) && '' !== $options['thumbnail']
            ? $options['thumbnail']
            : $this->thumbnailUrl( $canonical, $id );
        $mode      = $this->modeFor( $canonical );

        return [
            'provider'       => $canonical,
            'id'             => $id,
            'mode'           => $mode,
            'thumbnail'      => $thumbnail,
            'iframe_url'     => 'iframe' === $mode ? $this->iframeUrl( $canonical, $id, $params ) : '',
            'embed_html'     => 'blockquote' === $mode ? $this->embedHtml( $canonical, $id ) : '',
            'widgets_script' => 'blockquote' === $mode ? $this->widgetsScript( $canonical ) : '',
            'title'          => $title,
            'params'         => $params,
        ];
    }

    /**
     * Returns the activation mode for the given provider.
     *
     * `iframe` providers (YouTube, Vimeo) swap the facade for an `<iframe>`;
     * `blockquote` providers (Twitter/X) swap it for an inline HTML block
     * enhanced by an external widgets script — Twitter's publish endpoint
     * sets `X-Frame-Options: SAMEORIGIN` and cannot be iframed.
     *
     * @since 1.0.0
     *
     * @param  string  $provider  Canonical provider name.
     */
    public function modeFor( string $provider ): string
    {
        return 'twitter' === $provider ? 'blockquote' : 'iframe';
    }

    /**
     * Returns the canonical iframe URL for the given embed.
     *
     * Blockquote-mode providers (Twitter/X) return an empty string — the JS
     * module reads `embed_html` and `widgets_script` for those instead.
     *
     * @since 1.0.0
     *
     * @param  string  $provider  Canonical provider name.
     * @param  string  $id  Provider-specific identifier.
     * @param  array<string, string>  $params  Extra query parameters.
     */
    public function iframeUrl( string $provider, string $id, array $params = [] ): string
    {
        switch ( $provider ) {
            case 'youtube':
                $base = sprintf( 'https://www.youtube-nocookie.com/embed/%s', $id );
                break;
            case 'vimeo':
                $base = sprintf( 'https://player.vimeo.com/video/%s', $id );
                break;
            default:
                return '';
        }

        if ( empty( $params ) ) {
            return $base;
        }

        return $base . '?' . http_build_query( $params );
    }

    /**
     * Returns the inline HTML that activates a blockquote-mode embed.
     *
     * Twitter/X widgets.js converts a `<blockquote class="twitter-tweet">`
     * containing a status URL into the rendered tweet. Returns an empty
     * string for iframe-mode providers.
     *
     * @since 1.0.0
     *
     * @param  string  $provider  Canonical provider name.
     * @param  string  $id  Provider-specific identifier.
     */
    public function embedHtml( string $provider, string $id ): string
    {
        if ( 'twitter' === $provider ) {
            return sprintf(
                '<blockquote class="twitter-tweet"><a href="https://twitter.com/i/status/%s"></a></blockquote>',
                $id,
            );
        }

        return '';
    }

    /**
     * Returns the URL of the widgets script that hydrates a blockquote embed.
     *
     * @since 1.0.0
     *
     * @param  string  $provider  Canonical provider name.
     */
    public function widgetsScript( string $provider ): string
    {
        return 'twitter' === $provider ? 'https://platform.twitter.com/widgets.js' : '';
    }

    /**
     * Returns a default thumbnail URL for the embed.
     *
     * Twitter has no first-party thumbnail endpoint so callers receive an
     * empty string; the Blade component renders an SVG placeholder when no
     * thumbnail is available.
     *
     * @since 1.0.0
     *
     * @param  string  $provider  Canonical provider name.
     * @param  string  $id  Provider-specific identifier.
     */
    public function thumbnailUrl( string $provider, string $id ): string
    {
        switch ( $provider ) {
            case 'youtube':
                return sprintf( 'https://i.ytimg.com/vi/%s/hqdefault.jpg', $id );
            case 'vimeo':
                return sprintf( 'https://vumbnail.com/%s.jpg', $id );
            default:
                return '';
        }
    }

    /**
     * Validates a provider-specific identifier.
     *
     * @since 1.0.0
     *
     * @param  string  $provider  Canonical provider name.
     * @param  string  $id  Caller-supplied identifier.
     */
    public function isValidId( string $provider, string $id ): bool
    {
        if ( '' === $id ) {
            return false;
        }

        return match ( $provider ) {
            'youtube' => 1 === preg_match( '/^[A-Za-z0-9_-]{6,20}$/', $id ),
            'vimeo'   => 1 === preg_match( '/^[0-9]{6,15}$/', $id ),
            'twitter' => 1 === preg_match( '/^[0-9]{6,25}$/', $id ),
            default   => false,
        };
    }

    /**
     * Normalizes the params map to a string-string array.
     *
     * @since 1.0.0
     *
     * @param  mixed  $params  Raw params input.
     *
     * @return array<string, string>
     */
    protected function normalizeParams( mixed $params ): array
    {
        if ( ! is_array( $params ) ) {
            return [];
        }

        $result = [];

        foreach ( $params as $key => $value ) {
            if ( ! is_string( $key ) || '' === $key ) {
                continue;
            }

            if ( is_bool( $value ) ) {
                $result[ $key ] = $value ? '1' : '0';

                continue;
            }

            if ( is_scalar( $value ) ) {
                $result[ $key ] = (string) $value;
            }
        }

        return $result;
    }

    /**
     * Resolves the title used for the facade's play button aria-label.
     *
     * @since 1.0.0
     *
     * @param  string  $provider  Canonical provider name.
     * @param  string|null  $title  Caller-supplied title.
     */
    protected function resolveTitle( string $provider, ?string $title ): string
    {
        if ( null !== $title && '' !== trim( $title ) ) {
            return $title;
        }

        return match ( $provider ) {
            'youtube' => 'YouTube video',
            'vimeo'   => 'Vimeo video',
            'twitter' => 'Tweet',
            default   => 'Embedded media',
        };
    }
}
