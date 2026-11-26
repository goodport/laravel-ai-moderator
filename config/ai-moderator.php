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
        'strict' => true,
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
    | Journal
    |--------------------------------------------------------------------------
    |
    | The journal package records calls automatically. These settings control
    | the automatic replay of successful moderation steps. TTL is in seconds;
    | a zero TTL disables replay. Checks listed in always_fresh_checks always
    | call the provider again.
    |
    */

    'journal' => [
        'replay' => [
            'enabled' => true,
            'ttl' => 86400,
            'scope' => null,
            'always_fresh_checks' => [],
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
