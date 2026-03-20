<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\UI\Overlays;

final class OverlayFactory
{
    /**
     * @param array<string, mixed> $payload
     *
     * @return list<array<string, mixed>>
     */
    public function make(array $payload): array
    {
        $overlays = [];

        foreach ((array) ($payload['actions'] ?? []) as $action) {
            $modal = $action['modal'] ?? null;

            if (!is_string($modal) || $modal === '') {
                continue;
            }

            $overlays[] = [
                'key' => $action['key'] ?? 'overlay',
                'modal' => $modal,
                'requires_confirmation' => (bool) ($action['requires_confirmation'] ?? false),
            ];
        }

        return $overlays;
    }
}
