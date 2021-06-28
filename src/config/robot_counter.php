<?php
return [
    'accepted_methods' => ['GET'], // List your accepted methods here. Ex: GET, PUT, POST, DELETE, OPTIONS
    'storage_path'     => storage_path('client-logs'),
    'prefix_log_file'  => 'robot-counter',
    'app_int'          => 1, // 123job
    'list_bot'         => [
        'googlebot',
        'bingbot',
        'slurp',
    ],
    'max_day_log'      => 5,
];