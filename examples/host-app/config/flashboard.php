<?php

declare(strict_types=1);

return [
    'path' => 'admin',
    'discovery' => [
        'providers' => [],
        'resources' => [
            App\Flashboard\DemoOrdersResource::class,
        ],
        'pages' => [
            App\Flashboard\DemoReviewQueuePage::class,
        ],
    ],
];
