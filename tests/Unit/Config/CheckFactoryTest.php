<?php

namespace Goodport\AiModerator\Tests\Unit\Config;

use ArrayObject;
use Goodport\AiModerator\Checks\CheckKind;
use Goodport\AiModerator\Config\CheckFactory;
use Goodport\AiModerator\Config\InvalidConfigurationException;
use Goodport\AiModerator\Tests\TestCase;
use SplStack;

final class CheckFactoryTest extends TestCase
{
    /**
     * The profile and the check are merged in the right order.
     */
    public function test_merges_defaults_profile_and_check(): void
    {
        $this->app['config']->set('ai-moderator.defaults.second_pass', [
            'enabled' => true,
            'trigger' => 'on_reject',
            'merge' => 'approve_wins',
        ]);

        $this->setProfile('reviews', [
            'context' => 'A platform for reviews.',
            'language' => 'en',
            'model' => 'profile-model',
            'middleware' => [ArrayObject::class],
            'second_pass' => ['provider' => 'anthropic'],
        ]);

        $this->setCheck('post_body', $this->verdictCheck([
            'profile' => 'reviews',
            'model' => 'check-model',
            'middleware' => [SplStack::class],
            'second_pass' => ['trigger' => 'always'],
        ]));

        $definition = app(CheckFactory::class)->make('post_body');

        $this->assertSame(CheckKind::Verdict, $definition->kind());
        $this->assertSame('check-model', $definition->config->model);
        $this->assertSame('A platform for reviews.', $definition->config->context);
        $this->assertSame('en', $definition->config->language);
        $this->assertContains(ArrayObject::class, $definition->config->middleware);
        $this->assertContains(SplStack::class, $definition->config->middleware);
        $this->assertTrue($definition->config->secondPass->enabled);
        $this->assertSame('always', $definition->config->secondPass->trigger->value);
        $this->assertSame('anthropic', $definition->config->secondPass->provider);
        $this->assertSame('approve_wins', $definition->config->secondPass->merge->value);
    }

    /**
     * Check-only keys are not inherited from a profile.
     */
    public function test_profile_cannot_override_check_only_keys(): void
    {
        $this->setProfile('reviews', [
            'instructions' => 'Profile instructions.',
            'violations' => [
                'profile' => ['severity' => 'high', 'blocking' => true],
            ],
        ]);

        $this->setCheck('post_body', $this->verdictCheck([
            'profile' => 'reviews',
            'instructions' => 'Check instructions.',
        ]));

        $definition = app(CheckFactory::class)->make('post_body');

        $this->assertSame('Check instructions.', $definition->config->instructions);
        $this->assertArrayHasKey('bad', $definition->violations);
        $this->assertArrayNotHasKey('profile', $definition->violations);
    }

    /**
     * `auto` on a check does not erase the profile language.
     */
    public function test_auto_language_falls_through_to_profile(): void
    {
        $this->setProfile('reviews', ['language' => 'en']);
        $this->setCheck('post_body', $this->verdictCheck([
            'profile' => 'reviews',
            'language' => 'auto',
        ]));

        $definition = app(CheckFactory::class)->make('post_body');

        $this->assertSame('en', $definition->config->language);
    }

    /**
     * A profile can disable its checks when the check does not override it.
     */
    public function test_profile_enabled_is_inherited(): void
    {
        $this->setProfile('reviews', ['enabled' => false]);
        $this->setCheck('post_body', $this->verdictCheck([
            'profile' => 'reviews',
        ]));

        $definition = app(CheckFactory::class)->make('post_body');

        $this->assertFalse($definition->enabled());
    }

    /**
     * An unknown profile is a configuration error.
     */
    public function test_unknown_profile_throws(): void
    {
        $this->setCheck('post_body', $this->verdictCheck(['profile' => 'missing']));

        $this->expectException(InvalidConfigurationException::class);

        app(CheckFactory::class)->make('post_body');
    }

    /**
     * An invalid declaration fails before the model is called.
     */
    public function test_invalid_declaration_throws(): void
    {
        $this->setCheck('post_body', [
            'kind' => 'verdict',
            'input' => ['fields' => ['body' => ['type' => 'text', 'label' => 'Body', 'max_chars' => 100]]],
            'violations' => ['bad' => ['severity' => 'high', 'blocking' => true]],
        ]);

        $this->expectException(InvalidConfigurationException::class);

        app(CheckFactory::class)->make('post_body');
    }

    /**
     * An invalid middleware shape gives a clear configuration error.
     */
    public function test_invalid_middleware_shape_throws(): void
    {
        $this->setCheck('post_body', $this->verdictCheck(['middleware' => 'bad']));

        $this->expectException(InvalidConfigurationException::class);

        app(CheckFactory::class)->make('post_body');
    }

    /**
     * An invalid second_pass shape gives a clear configuration error.
     */
    public function test_invalid_second_pass_shape_throws(): void
    {
        $this->setCheck('post_body', $this->verdictCheck(['second_pass' => 'bad']));

        $this->expectException(InvalidConfigurationException::class);

        app(CheckFactory::class)->make('post_body');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function verdictCheck(array $overrides = []): array
    {
        return array_replace([
            'kind' => 'verdict',
            'input' => [
                'fields' => [
                    'body' => ['type' => 'text', 'label' => 'Body', 'max_chars' => 100],
                ],
            ],
            'instructions' => 'Reject bad content.',
            'violations' => [
                'bad' => ['severity' => 'high', 'blocking' => true, 'description' => 'Bad content'],
            ],
        ], $overrides);
    }
}
