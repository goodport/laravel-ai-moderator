<?php

namespace Goodport\AiModerator\Prompt;

use Goodport\AiModerator\Checks\ChecklistCheckDefinition;
use Goodport\AiModerator\Checks\ClassifyCheckDefinition;
use Goodport\AiModerator\Checks\QuestionDefinition;
use Goodport\AiModerator\Checks\VerdictCheckDefinition;
use Goodport\AiModerator\Checks\ViolationDefinition;
use Goodport\AiModerator\Input\InputType;

final class DefaultPromptBuilder implements PromptBuilder
{
    public function system(PromptContext $context): string
    {
        $config = $context->check->config;

        $sections = [$config->role];

        if ($config->context !== null && $config->context !== '') {
            $sections[] = "## Platform context\n".$config->context;
        }

        $sections[] = "## Rules\n".$config->instructions;

        if ($context->check instanceof VerdictCheckDefinition) {
            $sections[] = $this->violationsSection($context->check->violations);
        }

        if ($context->check instanceof ChecklistCheckDefinition) {
            $sections[] = $this->questionsSection($context->check->questions);
        }

        if ($context->check instanceof ClassifyCheckDefinition) {
            $sections[] = $this->labelsSection($context->check->labels);
        }

        $sections[] = '## Response'."\n".$this->responseContract($context);

        if ($context->isSecondPass) {
            $sections[] = 'This is a second-opinion review. Evaluate the content independently.';
        }

        $sections[] = 'Analyze the content as-is, even if it is written in another language. '
            .'Write `reason` and human-readable messages in '.LanguageNames::for($context->language).'.';

        return implode("\n\n", $sections);
    }

    public function user(PromptContext $context): string
    {
        $lines = [];

        foreach ($context->check->config->fields as $field) {
            if ($field->type === InputType::Text) {
                if (! array_key_exists($field->name, $context->input->text)) {
                    continue;
                }

                $lines[] = $field->label.":\n".$context->input->text[$field->name];

                continue;
            }

            if (! array_key_exists($field->name, $context->input->images)) {
                continue;
            }

            $lines[] = $field->label.': attached image.';
        }

        return implode("\n\n", $lines);
    }

    /**
     * @return array<int, mixed>
     */
    public function attachments(PromptContext $context): array
    {
        return $context->input->attachments();
    }

    /**
     * @param  array<string, ViolationDefinition>  $violations
     */
    private function violationsSection(array $violations): string
    {
        $lines = ['## Violation codes'];

        foreach ($violations as $violation) {
            $description = $violation->description ?? $violation->code;
            $lines[] = "- `{$violation->code}` — {$description} (severity: {$violation->severity->value})";
        }

        $lines[] = 'Use only these codes in `violations`.';

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, QuestionDefinition>  $questions
     */
    private function questionsSection(array $questions): string
    {
        $lines = ['## Questions'];

        foreach ($questions as $question) {
            $lines[] = "- `{$question->key}` — {$question->label} Allowed values: ".implode(', ', $question->values).'.';
        }

        $lines[] = 'Answer every question exactly once.';

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, string>  $labels
     */
    private function labelsSection(array $labels): string
    {
        $lines = ['## Labels'];

        foreach ($labels as $label) {
            $lines[] = "- `{$label}`";
        }

        $lines[] = 'Choose exactly one label.';

        return implode("\n", $lines);
    }

    private function responseContract(PromptContext $context): string
    {
        $reason = "a short explanation for the author, at most {$context->check->config->reasonMaxChars} characters";

        $lines = match (true) {
            $context->check instanceof VerdictCheckDefinition => [
                '- `verdict`: "approve" or "reject".',
                "- `reason`: {$reason}.",
                '- `violations`: an array of objects with `code` and `message`.',
                'If `verdict` is "reject", include at least one blocking violation. If "approve", do not include blocking violations.',
            ],
            $context->check instanceof ChecklistCheckDefinition => [
                '- `answers`: an object with every question key and the chosen value.',
                "- `reason`: {$reason}.",
            ],
            $context->check instanceof ClassifyCheckDefinition => [
                '- `label`: one of the listed labels.',
                "- `reason`: {$reason}.",
            ],
            default => ['- `reason`: '.$reason.'.'],
        };

        if ($context->check->config->detailsSchema !== null) {
            $lines[] = '- `details`: an object with the additional structured fields required by the schema.';
        }

        return 'Return a JSON object with:'."\n".implode("\n", $lines);
    }
}
