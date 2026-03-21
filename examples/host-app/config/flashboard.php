<?php

declare(strict_types=1);

return [
    'path' => 'admin',
    'discovery' => [
        'providers' => [],
        'resources' => [
            App\Flashboard\OrdersResource::class,
        ],
        'pages' => [
            App\Flashboard\ReviewQueuePage::class,
        ],
    ],
];
