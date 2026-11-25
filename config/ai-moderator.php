<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    |
    | Every check inherits these values. A check or a profile may override
    | any scalar. Middleware is merged instead of replaced.
    |
    */

    'defaults' => [
        'provider' => env('AI_MODERATOR_PROVIDER'),
        'model' => env('AI_MODERATOR_MODEL'),
        'timeout' => 60,
        'language' => 'auto',
        'context' => null,
        'role' => 'You are a content moderation assistant.',
        'reason_max_chars' => 300,
        'on_error' => 'undecided',
        'middleware' => [],
        'second_pass' => [
            'enabled' => false,
            'trigger' => 'on_reject',
            'merge' => 'approve_wins',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Profiles
    |--------------------------------------------------------------------------
    |
    | Shared settings and platform context for a group of checks. A check
    | refers to a profile by its key.
    |
    */

    'profiles' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Checks
    |--------------------------------------------------------------------------
    |
    | Each check is either an array declaration or a class name that
    | implements Goodport\AiModerator\Checks\Check.
    |
    */

    'checks' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Policies
    |--------------------------------------------------------------------------
    |
    | A policy runs several checks over the same input and builds one report.
    |
    */

    'policies' => [
        //
    ],

];
