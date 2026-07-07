<?php

/**
 * Shared setup helpers for performance AI agent tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace Tests\Feature\Ai;

use ArtisanPackUI\Ai\Contracts\AgentPrompter;
use ArtisanPackUI\Ai\Contracts\CredentialResolver;
use ArtisanPackUI\Ai\Credentials\ChainedCredentialResolver;
use ArtisanPackUI\Ai\Credentials\Credentials;
use Illuminate\Support\Facades\Gate;
use Tests\Support\FakeAgentPrompter;

/**
 * Registers a fake prompter and stub credentials for both performance features.
 *
 * @package    ArtisanPack_UI
 * @subpackage Performance
 *
 * @since      1.1.0
 */
class AiAgentTestSetup
{
    /**
     * Prepare the container so agents can run against a fake prompter.
     *
     * @since 1.1.0
     *
     * @param  \Illuminate\Foundation\Application  $app  Application instance.
     *
     * @return FakeAgentPrompter The bound fake prompter.
     */
    public static function bootstrap( $app ): FakeAgentPrompter
    {
        /** @var ChainedCredentialResolver $resolver */
        $resolver = $app->make( CredentialResolver::class );
        $resolver->setOverride(
            new Credentials( provider: 'anthropic', apiKey: 'sk-test', defaultModel: 'claude-sonnet-4-6' ),
        );
        $resolver->useStore( fn () => null );

        $prompter = new FakeAgentPrompter();
        $app->instance( AgentPrompter::class, $prompter );

        foreach (
            [
                'performance.query_insight',
                'performance.optimization_suggestion',
            ] as $key
        ) {
            $app['config']->set( "artisanpack.ai.features.{$key}.enabled", true );
        }

        // Allow the shipped `performance.ai.use` Gate by default so the
        // happy-path controller tests don't need to construct an Eloquent
        // User. Tests that want to exercise the 403 path override this
        // Gate back to `false` before calling the endpoint.
        Gate::define( 'performance.ai.use', static fn ( $user = null ): bool => true );

        return $prompter;
    }
}
