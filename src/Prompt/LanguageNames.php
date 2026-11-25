<?php

namespace Goodport\AiModerator\Prompt;

final class LanguageNames
{
    /**
     * @var array<string, string>
     */
    private const NAMES = [
        'en' => 'English',
        'ru' => 'Russian',
    ];

    public static function for(string $locale): string
    {
        $normalized = strtolower(str_replace('_', '-', $locale));
        $base = explode('-', $normalized)[0];

        return self::NAMES[$base] ?? $locale;
    }
}
