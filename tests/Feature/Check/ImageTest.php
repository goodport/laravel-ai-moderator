<?php

namespace Goodport\AiModerator\Tests\Feature\Check;

use Goodport\AiModerator\Agents\ModerationAgent;
use Goodport\AiModerator\Facades\AiModerator;
use Goodport\AiModerator\Tests\TestCase;
use Laravel\Ai\Files\RemoteImage;
use Laravel\Ai\Prompts\AgentPrompt;

final class ImageTest extends TestCase
{
    /**
     * An image URL becomes an attachment and the field label appears in the prompt.
     */
    public function test_image_url_becomes_attachment(): void
    {
        $this->setCheck('post_cover', [
            'kind' => 'verdict',
            'input' => [
                'fields' => [
                    'body' => ['type' => 'text', 'label' => 'Body', 'max_chars' => 1000],
                    'cover' => ['type' => 'image', 'label' => 'Cover'],
                ],
            ],
            'instructions' => 'Review the cover.',
            'violations' => [
                'bad' => ['severity' => 'high', 'blocking' => true, 'description' => 'Bad cover'],
            ],
        ]);

        ModerationAgent::fake([
            ['verdict' => 'approve', 'reason' => 'Fine.', 'violations' => []],
        ]);

        AiModerator::check('post_cover')
            ->input([
                'body' => 'A post',
                'cover' => 'https://example.com/cover.jpg',
            ])
            ->run();

        ModerationAgent::assertPrompted(function (AgentPrompt $prompt): bool {
            $attachment = $prompt->attachments->first();

            return str_contains($prompt->prompt, 'Cover: attached image.')
                && $prompt->attachments->count() === 1
                && $attachment instanceof RemoteImage
                && $attachment->url === 'https://example.com/cover.jpg';
        });
    }
}
