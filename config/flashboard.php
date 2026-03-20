<?php

declare(strict_types=1);

return [
    'name' => env('FLASHBOARD_NAME', env('APP_NAME', 'Laravel').' Admin'),
    'path' => env('FLASHBOARD_PATH', 'admin'),
    'route_name_prefix' => 'flashboard.',
    'guard' => env('FLASHBOARD_GUARD'),
    'middleware' => [
        'web' => ['web'],
        'auth' => ['flashboard.auth'],
    ],
    'auth' => [
        'login_path' => 'login',
        'logout_path' => 'logout',
        'username' => 'email',
        'password' => 'password',
        'remember_key' => 'remember',
    ],
    'install' => [
        'publish_views' => true,
    ],
    'discovery' => [
        'providers' => [],
        'resources' => [],
        'pages' => [],
    ],
    'logging' => [
        'report_boot' => env('FLASHBOARD_REPORT_BOOT', false),
    ],
];
