<?php

namespace Goodport\AiModerator\Tests\Feature;

use Goodport\AiModerator\Facades\AiModerator;
use Goodport\AiModerator\Tests\TestCase;
use Illuminate\Support\Facades\Http;

final class StrictSchemaTest extends TestCase
{
    /**
     * The strict agent sends the strict JSON schema flag to the provider.
     */
    public function test_strict_schema_is_sent_to_provider(): void
    {
        $this->app['config']->set('ai.providers.openai.key', 'test-key');
        $this->setCheck('post_body', [
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
            'strict' => true,
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'id' => 'response-1',
                'model' => 'gpt-test',
                'status' => 'completed',
                'output' => [
                    [
                        'type' => 'message',
                        'status' => 'completed',
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => json_encode([
                                    'verdict' => 'approve',
                                    'reason' => 'Fine.',
                                    'violations' => [],
                                ], JSON_THROW_ON_ERROR),
                            ],
                        ],
                    ],
                ],
                'usage' => [
                    'input_tokens' => 1,
                    'output_tokens' => 1,
                ],
            ]),
        ]);

        AiModerator::check('post_body')->input(['body' => 'Hello'])->run();

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.openai.com/v1/responses'
                && $request['text']['format']['strict'] === true;
        });
    }

    /**
     * An OpenAI-compatible provider receives strict inside response_format.
     */
    public function test_strict_schema_is_sent_to_openai_compatible_provider(): void
    {
        $this->app['config']->set('ai.providers.openai-compatible.url', 'https://api.example.com/v1');
        $this->app['config']->set('ai.providers.openai-compatible.key', 'test-key');
        $this->setCheck('post_body', [
            'kind' => 'verdict',
            'provider' => 'openai-compatible',
            'model' => 'gemini-3.5-flash-lite',
            'input' => [
                'fields' => [
                    'body' => ['type' => 'text', 'label' => 'Body', 'max_chars' => 100],
                ],
            ],
            'instructions' => 'Reject bad content.',
            'violations' => [
                'bad' => ['severity' => 'high', 'blocking' => true],
            ],
            'strict' => true,
        ]);

        Http::fake([
            'api.example.com/*' => Http::response([
                'id' => 'chatcmpl-1',
                'object' => 'chat.completion',
                'model' => 'gemini-3.5-flash-lite',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => json_encode([
                                'verdict' => 'approve',
                                'reason' => 'Fine.',
                                'violations' => [],
                            ], JSON_THROW_ON_ERROR),
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 1,
                    'completion_tokens' => 1,
                    'total_tokens' => 2,
                ],
            ]),
        ]);

        AiModerator::check('post_body')->input(['body' => 'Hello'])->run();

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.example.com/v1/chat/completions'
                && $request['response_format']['json_schema']['strict'] === true;
        });
    }
}
