<?php

/**
 * HTML minifier service.
 *
 * Shrinks rendered HTML by removing comments and collapsing runs of
 * whitespace in text content, while leaving the inside of tags
 * (attribute values, attribute whitespace) and the contents of
 * whitespace-significant elements (`<pre>`, `<code>`, `<textarea>`,
 * `<script>`, `<style>` by default) untouched. The minifier reads
 * its preferences from `artisanpack.performance.html_minification.*`
 * so applications can disable individual passes (comment-strip,
 * whitespace-collapse, line-break preservation) without subclassing.
 *
 * Implementation: a single-pass tokenizer walks the document
 * distinguishing tags, comments, and text. Excluded-element bodies
 * are stashed with depth tracking so nested `<pre><pre>...</pre></pre>`
 * round-trips correctly. Regex passes are intentionally NOT applied
 * over the full document — applying `\s+` to the whole document
 * mangles whitespace inside attribute values (`alt="Hello   World"`
 * → `alt="Hello World"`) and turns the value-bearing payload of form
 * controls into something the server never saw. Likewise applying
 * the comment-strip regex over the whole document destroys any
 * `<!--` substring that legitimately lives inside an attribute value.
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
    protected const PLACEHOLDER_PREFIX = "\x02__PERF_MINIFY_PLACEHOLDER_";

    /**
     * Trailing token that closes a placeholder.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected const PLACEHOLDER_SUFFIX = "__\x02";

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

        if ( ! (bool) config( 'artisanpack.performance.html_minification.enabled', false ) ) {
            return $html;
        }

        // \x02 is an ASCII control character (STX) that practically never
        // appears in legitimate HTML. If a payload includes it, abort
        // minification entirely rather than risk corrupting the input —
        // returning the unminified bytes is the safe default.
        if ( false !== strpos( $html, "\x02" ) ) {
            return $html;
        }

        $excluded = $this->resolveExcludedElements();

        [ $stashed, $tokens ] = $this->stashExcludedElements( $html, $excluded );

        $removeComments     = (bool) config( 'artisanpack.performance.html_minification.remove_comments', true );
        $collapseWhitespace = (bool) config( 'artisanpack.performance.html_minification.remove_whitespace', true );
        $preserveBreaks     = (bool) config( 'artisanpack.performance.html_minification.preserve_line_breaks', false );

        $result = $this->processTokens( $stashed, $removeComments, $collapseWhitespace, $preserveBreaks );

        return $this->restoreExcludedElements( $result, $tokens );
    }

    /**
     * Returns the list of elements whose contents should be preserved.
     *
     * Configuration entries are normalized to lowercase and de-duplicated
     * so user configs that mix casing (e.g. `Pre`, `PRE`) don't produce
     * duplicate scan passes. Null / non-scalar entries are dropped so
     * passing `[null, 'pre']` survives strict-mode runtimes.
     *
     * @since 1.0.0
     *
     * @return array<int, string> The list of element names.
     */
    protected function resolveExcludedElements(): array
    {
        $configured = (array) config(
            'artisanpack.performance.html_minification.exclude_elements',
            [ 'pre', 'code', 'textarea', 'script', 'style' ],
        );

        $normalized = [];

        foreach ( $configured as $element ) {
            if ( ! is_scalar( $element ) ) {
                continue;
            }

            $name = strtolower( trim( (string) $element ) );

            if ( '' === $name ) {
                continue;
            }

            $normalized[ $name ] = true;
        }

        return array_keys( $normalized );
    }

    /**
     * Replaces excluded-element bodies with placeholders, preserving nesting.
     *
     * Walks the document one byte at a time looking for opening tags
     * that match an excluded element. When found, the matcher tracks
     * the nesting depth so `<pre><pre>x</pre></pre>` is captured as
     * one balanced block — the naive non-greedy regex would pair the
     * first `<pre>` with the first `</pre>` and leave a stray closing
     * tag in the output.
     *
     * Tags inside attribute values (e.g. `<a title="<pre>">`) are
     * skipped by the tokenizer's tag-aware loop so spurious matches
     * don't fire on content that looks tag-shaped inside quotes.
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
        if ( [] === $elements ) {
            return [ $html, [] ];
        }

        $set    = array_flip( $elements );
        $tokens = [];
        $output = '';
        $i      = 0;
        $len    = strlen( $html );

        while ( $i < $len ) {
            $lt = strpos( $html, '<', $i );

            if ( false === $lt ) {
                $output .= substr( $html, $i );
                break;
            }

            $output .= substr( $html, $i, $lt - $i );

            $tagInfo = $this->readTag( $html, $lt );

            if ( null === $tagInfo ) {
                // Not a real tag (e.g. stray '<' character). Copy the
                // byte verbatim and keep scanning.
                $output .= $html[ $lt ];
                $i       = $lt + 1;

                continue;
            }

            [ $tagName, $tagOpen, $isClose, $isSelfClose, $endPos ] = $tagInfo;

            if ( $isClose || $isSelfClose || ! isset( $set[ $tagName ] ) ) {
                // Pass-through: copy the tag and continue. (Self-closing
                // excluded tags have no body to stash.)
                $output .= $tagOpen;
                $i       = $endPos;

                continue;
            }

            // Find the matching close tag, accounting for nesting of the
            // same element (e.g. `<pre><pre>...</pre></pre>`).
            $depth      = 1;
            $scan       = $endPos;
            $bodyStart  = $endPos;

            while ( $scan < $len && $depth > 0 ) {
                $next = strpos( $html, '<', $scan );

                if ( false === $next ) {
                    break;
                }

                $inner = $this->readTag( $html, $next );

                if ( null === $inner ) {
                    $scan = $next + 1;
                    continue;
                }

                [ $innerName, $innerOpen, $innerIsClose, $innerIsSelfClose, $innerEnd ] = $inner;

                if ( $innerName === $tagName ) {
                    if ( $innerIsClose ) {
                        $depth--;

                        if ( 0 === $depth ) {
                            $body             = substr( $html, $bodyStart, $next - $bodyStart );
                            $token            = self::PLACEHOLDER_PREFIX . count( $tokens ) . self::PLACEHOLDER_SUFFIX;
                            $tokens[ $token ] = $body;
                            $output .= $tagOpen . $token . $innerOpen;
                            $scan      = $innerEnd;
                            $i         = $innerEnd;
                            $bodyStart = -1;

                            continue 2;
                        }
                    } elseif ( ! $innerIsSelfClose ) {
                        $depth++;
                    }
                }

                $scan = $innerEnd;
            }

            // Unbalanced opening tag (no matching close). Emit the rest
            // of the document verbatim — refusing to stash an unbalanced
            // block keeps the original markup intact.
            $output .= substr( $html, $lt );
            $i       = $len;
        }

        return [ $output, $tokens ];
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
     * Walks the document and processes text, tags, and comments in one pass.
     *
     * Text segments are subject to whitespace collapse (and only the
     * text — not attribute values). Comments are dropped unless they're
     * IE conditional comments. Tag bodies (including attribute values)
     * are emitted verbatim.
     *
     * @since 1.0.0
     *
     * @param  string  $html  HTML to process.
     * @param  bool  $removeComments  Whether to drop non-IE comments.
     * @param  bool  $collapseWhitespace  Whether to collapse runs in text content.
     * @param  bool  $preserveBreaks  Whether collapsed runs that contained newlines become `\n`.
     */
    protected function processTokens( string $html, bool $removeComments, bool $collapseWhitespace, bool $preserveBreaks ): string
    {
        $output = '';
        $i      = 0;
        $len    = strlen( $html );

        while ( $i < $len ) {
            $lt = strpos( $html, '<', $i );

            if ( false === $lt ) {
                $output .= $this->processText( substr( $html, $i ), $collapseWhitespace, $preserveBreaks );
                break;
            }

            if ( $lt > $i ) {
                $output .= $this->processText( substr( $html, $i, $lt - $i ), $collapseWhitespace, $preserveBreaks );
            }

            // Comment detection — `<!--` ... `-->`. IE conditional
            // comments (`<!--[if ...]>` and `<![endif]-->`) are
            // preserved when remove_comments is on because they remain
            // semantically active code in legacy browsers.
            if ( '<!--' === substr( $html, $lt, 4 ) ) {
                $end = strpos( $html, '-->', $lt + 4 );

                if ( false === $end ) {
                    $output .= substr( $html, $lt );
                    break;
                }

                $commentEnd = $end + 3;
                $comment    = substr( $html, $lt, $commentEnd - $lt );

                if ( $removeComments && ! $this->isPreservedComment( $comment ) ) {
                    // Drop the comment AND any whitespace immediately
                    // before/after it that would otherwise leave a hole.
                    $i = $commentEnd;
                    continue;
                }

                $output .= $comment;
                $i       = $commentEnd;

                continue;
            }

            $tagInfo = $this->readTag( $html, $lt );

            if ( null === $tagInfo ) {
                $output .= $html[ $lt ];
                $i       = $lt + 1;

                continue;
            }

            [ , $tagOpen, , , $endPos ] = $tagInfo;

            $output .= $tagOpen;
            $i       = $endPos;
        }

        return $output;
    }

    /**
     * Reports whether a comment should be preserved (IE conditionals).
     *
     * Recognizes both classic downlevel-hidden (`<!--[if IE]>...<![endif]-->`)
     * and downlevel-revealed (`<!--<![if !IE]>...<![endif]-->`) shapes.
     *
     * @since 1.0.0
     *
     * @param  string  $comment  Full comment text including delimiters.
     */
    protected function isPreservedComment( string $comment ): bool
    {
        return 1 === preg_match( '/^<!--\s*(?:\[if\s|<!\[)/i', $comment );
    }

    /**
     * Collapses whitespace inside a text segment.
     *
     * When `$preserveBreaks` is true, whitespace runs that CONTAIN a
     * newline are collapsed to a single `\n`; runs without newlines
     * are still collapsed to a single space. The naive
     * `preg_replace('/\s+/', "\n", ...)` would turn every intra-word
     * space into a newline (rendering `Hello World` as `Hello\nWorld`).
     *
     * @since 1.0.0
     *
     * @param  string  $text  Text content between tags.
     * @param  bool  $collapse  Whether to collapse whitespace runs at all.
     * @param  bool  $preserveBreaks  Whether newline-bearing runs survive as a single `\n`.
     */
    protected function processText( string $text, bool $collapse, bool $preserveBreaks ): string
    {
        if ( '' === $text || ! $collapse ) {
            return $text;
        }

        if ( $preserveBreaks ) {
            // Two-pass: any whitespace run that contains a CR or LF
            // collapses to a single `\n`; remaining horizontal-only
            // whitespace runs collapse to a single space. The naive
            // `/\s+/ → "\n"` would turn every intra-word space into a
            // newline (`Hello World` → `Hello\nWorld`). Note: `\h`
            // is PCRE's horizontal-whitespace class — using `\v` here
            // would WRONGLY match newlines because PCRE expands `\v`
            // inside a character class to its vertical-whitespace shorthand.
            $text = (string) preg_replace( '/\s*[\r\n]\s*/', "\n", $text );
            $text = (string) preg_replace( '/\h+/', ' ', $text );

            return $text;
        }

        return (string) preg_replace( '/\s+/', ' ', $text );
    }

    /**
     * Reads a single tag starting at `$pos` and returns its metadata.
     *
     * Parses through quoted attribute values so a literal `>` inside
     * an attribute (e.g. `<a title="x>y">`) doesn't end the tag early.
     * Returns null when the input at `$pos` isn't actually a tag
     * (HTML entities like `&lt;`, stray `<` followed by whitespace).
     *
     * @since 1.0.0
     *
     * @param  string  $html  Full document.
     * @param  int  $pos  Position of `<`.
     *
     * @return array{0: string, 1: string, 2: bool, 3: bool, 4: int}|null Tag name (lower), full tag text, is-close, is-self-close, end position.
     */
    protected function readTag( string $html, int $pos ): ?array
    {
        $len = strlen( $html );

        if ( $pos >= $len || '<' !== $html[ $pos ] ) {
            return null;
        }

        $next = $html[ $pos + 1 ] ?? '';

        // `<!` and `<?` are not element tags — comments / doctype /
        // processing instructions are handled elsewhere.
        if ( '!' === $next || '?' === $next ) {
            return null;
        }

        $isClose = '/' === $next;
        $cursor  = $pos + 1 + ( $isClose ? 1 : 0 );

        // Tag name must start with an ASCII letter.
        if ( $cursor >= $len || 1 !== preg_match( '/[a-zA-Z]/', $html[ $cursor ] ) ) {
            return null;
        }

        $nameStart = $cursor;

        while ( $cursor < $len && 1 === preg_match( '/[a-zA-Z0-9:_-]/', $html[ $cursor ] ) ) {
            $cursor++;
        }

        $name = strtolower( substr( $html, $nameStart, $cursor - $nameStart ) );

        // Walk to the closing `>`, ducking through quoted attribute
        // values so embedded `>` characters don't end the tag early.
        while ( $cursor < $len ) {
            $char = $html[ $cursor ];

            if ( '"' === $char || "'" === $char ) {
                $quoteEnd = strpos( $html, $char, $cursor + 1 );

                if ( false === $quoteEnd ) {
                    // Unterminated attribute value — treat as malformed
                    // and bail out so the original bytes survive.
                    return null;
                }

                $cursor = $quoteEnd + 1;

                continue;
            }

            if ( '>' === $char ) {
                $endPos      = $cursor + 1;
                $tagText     = substr( $html, $pos, $endPos - $pos );
                $isSelfClose = '/' === ( $html[ $cursor - 1 ] ?? '' );

                return [ $name, $tagText, $isClose, $isSelfClose, $endPos ];
            }

            $cursor++;
        }

        return null;
    }
}
