<?php

/**
 * HTML minifier service.
 *
 * Shrinks rendered HTML by removing comments and collapsing runs of
 * whitespace between tags, while leaving content inside whitespace-
 * significant elements (`<pre>`, `<code>`, `<textarea>`, `<script>`,
 * `<style>` by default) untouched. The minifier reads its preferences
 * from `artisanpack.performance.html_minification.*` so applications
 * can disable individual passes (comment-strip, whitespace-collapse,
 * line-break preservation) without subclassing.
 *
 * The class operates in two passes: first it extracts the contents of
 * excluded elements into placeholders so the regexes that follow can
 * be aggressive without corrupting `<pre>` payloads, then it restores
 * the placeholders. IE conditional comments (`<!--[if ...]>...<![endif]-->`)
 * are intentionally preserved when comment removal is enabled — they
 * are semantically code, not commentary, and stripping them breaks
 * targeted legacy-browser fallbacks that still ship in real apps.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Output;

/**
 * HTML minifier class.
 *
 *
 * @since      1.0.0
 */
class HtmlMinifier
{
    /**
     * Placeholder token used to swap excluded-element bodies in and out.
     *
     * The token is intentionally exotic so it can't collide with anything
     * a Blade template would legitimately emit. The trailing index is
     * appended at runtime.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected const PLACEHOLDER_PREFIX = "\x00__PERF_MINIFY_PLACEHOLDER_";

    /**
     * Trailing token that closes a placeholder.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected const PLACEHOLDER_SUFFIX = "__\x00";

    /**
     * Minifies the supplied HTML according to current configuration.
     *
     * When the `html_minification.enabled` flag is false the input is
     * returned untouched so the minifier can be left wired into the
     * response pipeline without any cost at runtime.
     *
     * @since 1.0.0
     *
     * @param  string  $html  The HTML to minify.
     *
     * @return string The minified HTML, or the original when minification is disabled.
     */
    public function minify( string $html ): string
    {
        if ( '' === $html ) {
            return $html;
        }

        if ( ! (bool) config( 'artisanpack.performance.html_minification.enabled', true ) ) {
            return $html;
        }

        $excluded = $this->resolveExcludedElements();

        [ $stashed, $tokens ] = $this->stashExcludedElements( $html, $excluded );

        if ( (bool) config( 'artisanpack.performance.html_minification.remove_comments', true ) ) {
            $stashed = $this->removeComments( $stashed );
        }

        if ( (bool) config( 'artisanpack.performance.html_minification.remove_whitespace', true ) ) {
            $stashed = $this->collapseWhitespace(
                $stashed,
                (bool) config( 'artisanpack.performance.html_minification.preserve_line_breaks', false ),
            );
        }

        return $this->restoreExcludedElements( $stashed, $tokens );
    }

    /**
     * Returns the list of elements whose contents should be preserved.
     *
     * Configuration entries are normalized to lowercase and de-duplicated
     * so user configs that mix casing (e.g. `Pre`, `PRE`) don't produce
     * duplicate scan passes. `style` is force-added because mangling a
     * `<style>` block's whitespace can change selector parsing in
     * pathological cases (e.g. attribute selectors with literal spaces).
     *
     * @since 1.0.0
     *
     * @return array<int, string> The list of element names.
     */
    protected function resolveExcludedElements(): array
    {
        $configured = (array) config(
            'artisanpack.performance.html_minification.exclude_elements',
            [ 'pre', 'code', 'textarea', 'script' ],
        );

        $normalized = array_map(
            static fn ( $element ): string => strtolower( trim( (string) $element ) ),
            $configured,
        );

        $normalized = array_filter( $normalized, static fn ( string $element ): bool => '' !== $element );

        // Force-add `style` so users who scope their excludes to scripts only
        // still get correct CSS rendering. The cost is negligible — no extra
        // passes if no `<style>` block exists.
        $normalized[] = 'style';

        return array_values( array_unique( $normalized ) );
    }

    /**
     * Replaces excluded-element bodies with placeholders.
     *
     * The contents are stashed into the `$tokens` map keyed by their
     * placeholder so the restore step can swap them back verbatim. The
     * outer tags are LEFT IN the markup so whitespace collapsing can
     * still tidy up the inter-tag whitespace AROUND them.
     *
     * @since 1.0.0
     *
     * @param  string  $html  The HTML being processed.
     * @param  array<int, string>  $elements  Tag names to stash.
     *
     * @return array{0: string, 1: array<string, string>} The stashed HTML and token map.
     */
    protected function stashExcludedElements( string $html, array $elements ): array
    {
        $tokens  = [];
        $counter = 0;

        foreach ( $elements as $element ) {
            $pattern = sprintf(
                '#(<%1$s\b[^>]*>)(.*?)(</%1$s\s*>)#is',
                preg_quote( $element, '#' ),
            );

            $html = (string) preg_replace_callback(
                $pattern,
                function ( array $matches ) use ( &$tokens, &$counter ): string {
                    $token            = self::PLACEHOLDER_PREFIX . $counter++ . self::PLACEHOLDER_SUFFIX;
                    $tokens[ $token ] = $matches[2];

                    return $matches[1] . $token . $matches[3];
                },
                $html,
            );
        }

        return [ $html, $tokens ];
    }

    /**
     * Restores stashed element bodies into the minified output.
     *
     * The substitution uses `strtr()` so the operation is a single
     * linear pass and the placeholders cannot recursively trigger
     * each other (a regex-based restore could chain replacements if
     * one stashed body happened to contain another's placeholder
     * text).
     *
     * @since 1.0.0
     *
     * @param  string  $html  The HTML containing placeholders.
     * @param  array<string, string>  $tokens  The placeholder → original content map.
     *
     * @return string The HTML with stashed bodies restored.
     */
    protected function restoreExcludedElements( string $html, array $tokens ): string
    {
        if ( [] === $tokens ) {
            return $html;
        }

        return strtr( $html, $tokens );
    }

    /**
     * Removes HTML comments while preserving IE conditional comments.
     *
     * The pattern matches `<!--` ... `-->` non-greedily and excludes
     * blocks starting with `[if` (IE conditional comments) and `<![`
     * (downlevel-revealed conditional comments) so legacy markup that
     * targets specific IE versions still works after minification.
     *
     * @since 1.0.0
     *
     * @param  string  $html  The HTML to scrub.
     *
     * @return string The HTML with comments removed.
     */
    protected function removeComments( string $html ): string
    {
        return (string) preg_replace(
            '/<!--(?!\s*(?:\[if\s|<!\[))(?:(?!-->).)*-->/s',
            '',
            $html,
        );
    }

    /**
     * Collapses runs of whitespace between markup.
     *
     * When `$preserveLineBreaks` is true, each run is collapsed to a
     * single newline; otherwise to a single space. Whitespace between
     * adjacent tags (`>   <`) is removed entirely so opening/closing
     * tags sit flush against one another.
     *
     * @since 1.0.0
     *
     * @param  string  $html  The HTML to compress.
     * @param  bool  $preserveLineBreaks  Whether single newlines should survive.
     *
     * @return string The compressed HTML.
     */
    protected function collapseWhitespace( string $html, bool $preserveLineBreaks ): string
    {
        $separator = $preserveLineBreaks ? "\n" : ' ';

        // Collapse repeated whitespace runs first. The `\s` class includes
        // tabs and newlines so a single pass handles every variant.
        $html = (string) preg_replace( '/\s+/', $separator, $html );

        // Strip whitespace that sits between tags (`>  <`) so adjacent
        // elements end up flush. This runs AFTER the global collapse so
        // any multi-character runs left by the first pass also disappear.
        $html = (string) preg_replace( '/>\s+</', '><', $html );

        return trim( $html );
    }
}
