<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\UI\Notifications;

use Pepperfm\Flashboard\UI\Contracts\UiPayloadContract;

final readonly class Notification implements UiPayloadContract
{
    private string $id;

    public function __construct(
        private string $level,
        private string $message,
    ) {
        $this->id = (string) str()->uuid();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'level' => $this->level,
            'message' => $this->message,
        ];
    }
}
