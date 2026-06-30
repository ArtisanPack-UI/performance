<?php

/**
 * Output buffer management for response transformations.
 *
 * Provides a thin, instance-scoped wrapper around PHP's output-buffering
 * primitives that owns only the buffers it opened itself. Response
 * transformations (HTML minification, resource-hint injection,
 * speculation-rules emission) need to capture rendered HTML, run an
 * ordered pipeline of mutators against it, and hand the result back to
 * the SAPI without disturbing buffers the framework or the application
 * opened above us.
 *
 * The class deliberately does NOT call `ob_get_level()` to decide when
 * to clean up — that level includes buffers we don't own (Symfony's
 * response buffering, PHP's `output_buffering` ini setting, anything an
 * application started before our middleware ran). Closing those would
 * silently swallow output the caller still owns. Instead the depth
 * counter tracks ONLY the calls we made, and `end()` pairs against the
 * matching `start()`.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Performance\Output;

use Throwable;

/**
 * Output buffer manager.
 *
 *
 * @since      1.0.0
 */
class OutputBuffer
{
    /**
     * Number of buffers this instance has opened and not yet closed.
     *
     * Tracked independently of `ob_get_level()` so `end()` only ever
     * closes buffers we opened with `start()` — closing arbitrary
     * outer buffers would swallow output the caller still owns.
     *
     * @since 1.0.0
     */
    protected int $depth = 0;

    /**
     * Begins a new output buffer.
     *
     * Each call to `start()` must be matched by a call to `end()` (or
     * `flush()`). Calls may nest — depth is tracked internally so the
     * pairing survives multiple `start()` / `end()` cycles within the
     * same request.
     *
     * @since 1.0.0
     */
    public function start(): void
    {
        ob_start();

        $this->depth++;
    }

    /**
     * Returns the contents of the current buffer without closing it.
     *
     * Returns the empty string when this instance has no open buffers,
     * even if PHP itself has a buffer active — reading another owner's
     * buffer is unsafe because the contents may include partial output
     * the framework intends to emit later.
     *
     * @since 1.0.0
     */
    public function get(): string
    {
        if ( 0 === $this->depth ) {
            return '';
        }

        $contents = ob_get_contents();

        return false === $contents ? '' : $contents;
    }

    /**
     * Closes the current buffer and returns its contents.
     *
     * Pairs with the most recent `start()` call. Returns the empty
     * string and is a no-op when no buffer was opened.
     *
     * @since 1.0.0
     */
    public function end(): string
    {
        if ( 0 === $this->depth ) {
            return '';
        }

        $contents = ob_get_clean();

        $this->depth--;

        return false === $contents ? '' : $contents;
    }

    /**
     * Closes the current buffer and writes its contents to the parent.
     *
     * Distinct from `end()` in that the captured output is forwarded
     * to the next-outer buffer (or the SAPI when none remains) rather
     * than returned to the caller — useful when a middleware wants to
     * apply transformations in place and let normal response flow
     * proceed.
     *
     * @since 1.0.0
     */
    public function flush(): void
    {
        if ( 0 === $this->depth ) {
            return;
        }

        ob_end_flush();

        $this->depth--;
    }

    /**
     * Reports whether this instance currently owns at least one open buffer.
     *
     * @since 1.0.0
     */
    public function isActive(): bool
    {
        return $this->depth > 0;
    }

    /**
     * Returns the number of buffers this instance has open.
     *
     * @since 1.0.0
     */
    public function depth(): int
    {
        return $this->depth;
    }

    /**
     * Applies an ordered pipeline of transformations to a string.
     *
     * Each transformer receives the previous transformer's return
     * value. Non-string returns are skipped so a misbehaving
     * transformer can't poison the chain by returning null/false; the
     * pre-transformer payload continues forward instead. Transformer
     * exceptions are NOT caught — callers control failure semantics by
     * wrapping the call.
     *
     * @since 1.0.0
     *
     * @param  string  $content  The starting payload.
     * @param  iterable<callable(string): mixed>  $transformers  Transformers to apply in order.
     *
     * @return string The fully-transformed content.
     */
    public function transform( string $content, iterable $transformers ): string
    {
        foreach ( $transformers as $transformer ) {
            if ( ! is_callable( $transformer ) ) {
                continue;
            }

            $result = $transformer( $content );

            if ( is_string( $result ) ) {
                $content = $result;
            }
        }

        return $content;
    }

    /**
     * Runs a producer inside a fresh buffer and returns the captured output.
     *
     * Guarantees the buffer is closed even when `$producer` throws —
     * without this safety net a thrown exception during view rendering
     * would leak an open buffer into subsequent requests on long-lived
     * SAPIs (Octane, Swoole, RoadRunner). The optional transformer
     * pipeline runs only on the success path; on exception the buffer
     * contents are discarded and the original exception is re-raised.
     *
     * @since 1.0.0
     *
     * @param  callable  $producer  Closure that emits the content to capture.
     * @param  iterable<callable(string): mixed>  $transformers  Optional pipeline.
     *
     * @return string The captured (and optionally transformed) output.
     */
    public function capture( callable $producer, iterable $transformers = [] ): string
    {
        $this->start();

        try {
            $producer();
        } catch ( Throwable $exception ) {
            if ( $this->isActive() ) {
                $this->end();
            }

            throw $exception;
        }

        $content = $this->end();

        return $this->transform( $content, $transformers );
    }

    /**
     * Force-closes every buffer this instance still owns.
     *
     * Intended for Octane-style request teardown where a previous
     * request may have leaked open buffers. The loop iterates by our
     * own depth counter (not `ob_get_level()`) so we never reach into
     * buffers another owner opened. The `@ob_end_clean()` result is
     * deliberately ignored — if the buffer was already torn down by
     * something else, we still want to decrement so the counter
     * doesn't permanently lock the manager into an "always active"
     * state.
     *
     * @since 1.0.0
     */
    public function reset(): void
    {
        while ( $this->depth > 0 ) {
            @ob_end_clean();

            $this->depth--;
        }
    }
}
