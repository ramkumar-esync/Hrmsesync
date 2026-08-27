<?php

use Monolog\Handler\StreamHandler;

return [
    'default' => env('LOG_CHANNEL', 'stack'),

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['daily'],
            'ignore_exceptions' => false,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14,
            'replace_placeholders' => true,
        ],

        /*
         * Anything that changes what someone is owed or entitled to. Kept
         * separate and retained far longer than application logs, because these
         * are the entries you need when a figure is disputed months later.
         */
        'audit' => [
            'driver' => 'daily',
            'path' => storage_path('logs/audit.log'),
            'level' => 'info',
            'days' => 730,
            'replace_placeholders' => true,
        ],

        'stderr' => [
            'driver' => 'monolog',
            'handler' => StreamHandler::class,
            'with' => ['stream' => 'php://stderr'],
        ],
    ],
];
