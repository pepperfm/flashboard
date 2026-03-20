<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\UI\Notifications;

use Pepperfm\Flashboard\UI\Contracts\UiPayloadContract;

final readonly class Notification implements UiPayloadContract
{
    public function __construct(
        private string $level,
        private string $message,
    ) {
    }

    public function toArray(): array
    {
        return [
            'level' => $this->level,
            'message' => $this->message,
        ];
    }
}
