<?php

return [
    /*
    | Voice recordings (lesson audio, character voices, listening questions,
    | record-yourself practice and the recording studio).
    |
    | Off by default: set LUGANDA_VOICE=true in .env to switch everything on.
    | Recordings already saved are kept either way.
    */
    'voice' => (bool) env('LUGANDA_VOICE', false),

    /*
    | Background music: "Mpola", a slow original piece (public/music/), quiet under the lessons.
    | Learners can switch it off with the 🎵 button; this switches it off for everyone.
    | Never played on the admin, Check or Studio pages.
    */
    'music' => (bool) env('LUGANDA_MUSIC', true),
    'music_file' => '/music/mpola-1.mp3', // a new piece gets a new name (files here are cached for a year)

    /*
    | Play recordings before a native speaker has checked them?
    |
    | true:  learners hear every recording straight away (generated ones show 🤖).
    |        Checked recordings come first, then human ones, then generated ones.
    |        Flagged and rejected recordings never play.
    | false: learners only hear recordings whose text and audio are both approved.
    */
    'play_unchecked' => (bool) env('LUGANDA_PLAY_UNCHECKED', true),

    /*
    | New guest accounts allowed per hour from one internet connection (IP address).
    | Generous, because a whole classroom or office may share one connection.
    */
    'guests_per_hour' => (int) env('LUGANDA_GUESTS_PER_HOUR', 100),

    /*
    | Generated audio (text-to-speech).
    |
    | Nothing is ever generated automatically: only when an admin presses
    | "Generate" (Admin → Generate audio) or runs `php artisan app:generate-audio`.
    | Every generated recording waits for a reviewer's approval on the Check page.
    |
    |   LUGANDA_TTS=fake     a test beep, free, for trying the workflow (default)
    |   LUGANDA_TTS=sunbird  Sunbird AI Luganda voices (needs SUNBIRD_API_TOKEN)
    */
    'tts' => [
        'default' => env('LUGANDA_TTS', 'fake'),

        // Give new or re-spelled phrases and words a recording automatically when an
        // admin or reviewer saves them (only with a real provider, never test beeps).
        'auto' => (bool) env('LUGANDA_TTS_AUTO', true),

        // Safety rail: at most this many generation requests per day (all admins together).
        'daily_limit' => (int) env('LUGANDA_TTS_DAILY_LIMIT', 50),

        // Bump this if provider settings change, so "already generated" checks start afresh.
        'settings_version' => 1,

        'providers' => [
            'fake' => [
                'label' => 'Test beep (free, not Luganda)',
            ],
            'sunbird' => [
                'label' => 'Sunbird AI',
                'token' => env('SUNBIRD_API_TOKEN'),
                'url' => env('SUNBIRD_API_URL', 'https://api.sunbird.ai'),
                'voice' => env('SUNBIRD_VOICE', 'salt_lug_0001'),
                // Used when the voice list can't be fetched.
                'voices' => ['salt_lug_0001', 'waxal_lug_0002', 'waxal_lug_0003', 'waxal_lug_0004', 'waxal_lug_0005', 'waxal_lug_0006', 'waxal_lug_0007', 'waxal_lug_0008'],
                'timeout' => (int) env('SUNBIRD_TIMEOUT', 90),
                // Optional: a fixed seed makes the same text + voice sound the same each time.
                'seed' => env('SUNBIRD_SEED') !== null ? (int) env('SUNBIRD_SEED') : null,
            ],
        ],
    ],

    /*
    | Speaking practice (Phase 7).
    |
    | Learners record themselves, compare with the recording and say whether it
    | sounded the same. Each attempt is saved (who, which phrase, their verdict);
    | the learner's own audio is NEVER stored.
    |
    | Optional automatic checking (speech-to-text): the recording is sent to the
    | provider, compared with the phrase and thrown away. Off by default.
    |   LUGANDA_STT=off      no automatic checking (default)
    |   LUGANDA_STT=fake     for tests: "hears" a text file's contents
    |   LUGANDA_STT=sunbird  Sunbird AI speech-to-text, Luganda (uses SUNBIRD_API_TOKEN)
    */
    'stt' => [
        'default' => env('LUGANDA_STT', 'off'),

        // Safety rail: at most this many checks per day (all learners together).
        'daily_limit' => (int) env('LUGANDA_STT_DAILY_LIMIT', 100),

        // And at most this many per learner per hour.
        'per_learner_hourly' => (int) env('LUGANDA_STT_PER_LEARNER_HOURLY', 30),

        // Longest recording sent for checking, in kilobytes (a short phrase is far smaller).
        'max_kb' => (int) env('LUGANDA_STT_MAX_KB', 2048),

        'providers' => [
            'fake' => [
                'label' => 'Test listener (free, not real speech recognition)',
            ],
            'sunbird' => [
                'label' => 'Sunbird AI speech-to-text',
                'token' => env('SUNBIRD_API_TOKEN'),
                'url' => env('SUNBIRD_API_URL', 'https://api.sunbird.ai'),
                'timeout' => (int) env('SUNBIRD_STT_TIMEOUT', 60),
            ],
        ],
    ],
];
