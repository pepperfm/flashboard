<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\UI\Notifications;

final class NotificationCenter
{
    /**
     * @return list<array<string, string>>
     */
    public function current(): array
    {
        $notifications = [];
        foreach (['success', 'error', 'warning', 'info'] as $level) {
            $message = session()->get($level);
            if (!is_string($message) || $message === '') {
                continue;
            }

            $notifications[] = new Notification($level, $message)->toArray();
        }

        return $notifications;
    }
}
