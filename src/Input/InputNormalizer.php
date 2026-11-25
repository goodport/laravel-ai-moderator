<?php

namespace Goodport\AiModerator\Input;

use Goodport\AiModerator\Checks\CheckDefinition;
use Illuminate\Http\UploadedFile;
use Laravel\Ai\Files\Image;
use Stringable;

final class InputNormalizer
{
    public function __construct(
        private readonly Truncator $truncator = new Truncator,
    ) {}

    public function normalize(CheckDefinition $check, InputResolver|array $input): NormalizedInput
    {
        $values = $input instanceof InputResolver ? $input->fields() : $input;

        $text = [];
        $images = [];

        foreach ($check->config->fields as $field) {
            $value = $values[$field->name] ?? null;

            if ($value === null) {
                if ($field->required) {
                    throw InvalidInputException::missingField($check->key(), $field->name);
                }

                continue;
            }

            if ($field->type === InputType::Text) {
                $text[$field->name] = $this->truncator->truncate(
                    $this->asText($check->key(), $field->name, $value),
                    $field->maxChars,
                    $field->strategy,
                );

                continue;
            }

            $images[$field->name] = $this->asImage($check->key(), $field->name, $value);
        }

        return new NormalizedInput($text, $images);
    }

    private function asText(string $check, string $field, mixed $value): string
    {
        if ($value instanceof Stringable) {
            return (string) $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        throw InvalidInputException::invalidText($check, $field);
    }

    private function asImage(string $check, string $field, mixed $value): Image|UploadedFile
    {
        if ($value instanceof Image || $value instanceof UploadedFile) {
            return $value;
        }

        if (is_string($value)) {
            if (filter_var($value, FILTER_VALIDATE_URL)) {
                return Image::fromUrl($value);
            }

            if (is_file($value)) {
                return Image::fromPath($value);
            }
        }

        throw InvalidInputException::invalidImage($check, $field);
    }
}
