<?php

namespace Goodport\AiModerator\Tests\Unit\Config;

use Goodport\AiModerator\Config\CheckFactory;
use Goodport\AiModerator\Config\InvalidConfigurationException;
use Goodport\AiModerator\Tests\TestCase;

final class ModelRoleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('ai-moderator.defaults.provider', null);
        $this->app['config']->set('ai-moderator.defaults.model', null);
        $this->app['config']->set('ai-moderator.defaults.timeout', null);
    }

    /**
     * The check role wins over the profile and defaults roles.
     */
    public function test_role_precedence_is_check_then_profile_then_defaults(): void
    {
        $this->setModelRoles($this->roles());
        $this->app['config']->set('ai-moderator.defaults.model_role', 'light');
        $this->setProfile('reviews', ['model_role' => 'standard']);
        $this->setCheck('post_body', $this->verdictCheck([
            'profile' => 'reviews',
            'model_role' => 'strong',
        ]));

        $config = app(CheckFactory::class)->make('post_body')->config;

        $this->assertSame('provider-strong', $config->provider);
        $this->assertSame('strong-model', $config->model);
        $this->assertSame(30, $config->timeout);
    }

    /**
     * An explicit model overrides only the model and keeps the role provider.
     */
    public function test_explicit_model_overrides_only_model(): void
    {
        $this->setModelRoles($this->roles());
        $this->app['config']->set('ai-moderator.defaults.model_role', 'light');
        $this->setCheck('post_body', $this->verdictCheck([
            'model' => 'explicit-model',
        ]));

        $config = app(CheckFactory::class)->make('post_body')->config;

        $this->assertSame('provider-light', $config->provider);
        $this->assertSame('explicit-model', $config->model);
        $this->assertSame(10, $config->timeout);
    }

    /**
     * An explicit provider overrides only the provider.
     */
    public function test_explicit_provider_overrides_only_provider(): void
    {
        $this->setModelRoles($this->roles());
        $this->app['config']->set('ai-moderator.defaults.model_role', 'light');
        $this->setCheck('post_body', $this->verdictCheck([
            'provider' => 'explicit-provider',
        ]));

        $config = app(CheckFactory::class)->make('post_body')->config;

        $this->assertSame('explicit-provider', $config->provider);
        $this->assertSame('light-model', $config->model);
    }

    /**
     * An explicit timeout overrides the role timeout.
     */
    public function test_explicit_timeout_overrides_role_timeout(): void
    {
        $this->setModelRoles($this->roles());
        $this->app['config']->set('ai-moderator.defaults.model_role', 'light');
        $this->setCheck('post_body', $this->verdictCheck([
            'timeout' => 45,
        ]));

        $config = app(CheckFactory::class)->make('post_body')->config;

        $this->assertSame(45, $config->timeout);
    }

    /**
     * A check role wins over an explicit model at the profile level.
     */
    public function test_check_role_wins_over_profile_explicit_model(): void
    {
        $this->setModelRoles($this->roles());
        $this->app['config']->set('ai-moderator.defaults.model', 'default-model');
        $this->setProfile('reviews', [
            'model' => 'profile-model',
            'model_role' => 'standard',
        ]);
        $this->setCheck('post_body', $this->verdictCheck([
            'profile' => 'reviews',
            'model_role' => 'strong',
        ]));

        $config = app(CheckFactory::class)->make('post_body')->config;

        $this->assertSame('strong-model', $config->model);
    }

    /**
     * A profile role wins over an explicit model in defaults.
     */
    public function test_profile_role_wins_over_defaults_explicit_model(): void
    {
        $this->setModelRoles($this->roles());
        $this->app['config']->set('ai-moderator.defaults.model', 'default-model');
        $this->setProfile('reviews', ['model_role' => 'standard']);
        $this->setCheck('post_body', $this->verdictCheck([
            'profile' => 'reviews',
        ]));

        $config = app(CheckFactory::class)->make('post_body')->config;

        $this->assertSame('standard-model', $config->model);
    }

    /**
     * The second pass applies its role after inheriting the first pass.
     */
    public function test_second_pass_role_is_applied_after_first_pass(): void
    {
        $this->setModelRoles($this->roles());
        $this->setCheck('post_body', $this->verdictCheck([
            'model_role' => 'standard',
            'second_pass' => [
                'enabled' => true,
                'trigger' => 'on_reject',
                'merge' => 'approve_wins',
                'model_role' => 'strong',
            ],
        ]));

        $config = app(CheckFactory::class)->make('post_body')->config;

        $this->assertSame('standard-model', $config->model);
        $this->assertSame('strong-model', $config->secondPass->model);
        $this->assertSame('provider-strong', $config->secondPass->provider);
    }

    /**
     * A second-pass model override keeps the role provider.
     */
    public function test_second_pass_model_override_keeps_role_provider(): void
    {
        $this->setModelRoles($this->roles());
        $this->setCheck('post_body', $this->verdictCheck([
            'second_pass' => [
                'enabled' => true,
                'trigger' => 'on_reject',
                'merge' => 'approve_wins',
                'model_role' => 'strong',
                'model' => 'second-explicit-model',
            ],
        ]));

        $config = app(CheckFactory::class)->make('post_body')->config;

        $this->assertSame('second-explicit-model', $config->secondPass->model);
        $this->assertSame('provider-strong', $config->secondPass->provider);
    }

    /**
     * Explicit second-pass values override its role.
     */
    public function test_second_pass_explicit_values_override_role(): void
    {
        $this->setModelRoles($this->roles());
        $this->setCheck('post_body', $this->verdictCheck([
            'second_pass' => [
                'enabled' => true,
                'trigger' => 'on_reject',
                'merge' => 'approve_wins',
                'model_role' => 'strong',
                'model' => 'second-explicit-model',
                'provider' => 'second-explicit-provider',
                'timeout' => 90,
            ],
        ]));

        $config = app(CheckFactory::class)->make('post_body')->config;

        $this->assertSame('second-explicit-model', $config->secondPass->model);
        $this->assertSame('second-explicit-provider', $config->secondPass->provider);
        $this->assertSame(90, $config->secondPass->timeout);
    }

    /**
     * An unknown role is a clear configuration error.
     */
    public function test_unknown_role_throws(): void
    {
        $this->setModelRoles($this->roles());
        $this->setCheck('post_body', $this->verdictCheck(['model_role' => 'missing']));

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('missing');

        app(CheckFactory::class)->make('post_body');
    }

    /**
     * Package config does not read model settings from env.
     */
    public function test_package_config_has_no_env_fallbacks(): void
    {
        $config = file_get_contents(__DIR__.'/../../../config/ai-moderator.php');

        $this->assertStringNotContainsString('env(', $config);
    }

    /**
     * @return array<string, array{provider: string, model: string, timeout: int}>
     */
    private function roles(): array
    {
        return [
            'light' => ['provider' => 'provider-light', 'model' => 'light-model', 'timeout' => 10],
            'standard' => ['provider' => 'provider-standard', 'model' => 'standard-model', 'timeout' => 20],
            'strong' => ['provider' => 'provider-strong', 'model' => 'strong-model', 'timeout' => 30],
        ];
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
                'bad' => ['severity' => 'high', 'blocking' => true],
            ],
        ], $overrides);
    }
}
