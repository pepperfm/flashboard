<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\UI\States;

final class ScreenStateFactory
{
    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function make(array $payload): array
    {
        $table = $payload['table']['dataset'] ?? null;

        if (is_array($table) && isset($table['rows']) && $table['rows'] === []) {
            return [
                'kind' => 'empty',
                'message' => 'No records are available for this screen yet.',
            ];
        }
        if (($payload['resource']['page'] ?? null) === 'create') {
            return [
                'kind' => 'ready',
                'message' => 'Fill in the form to create a new record.',
            ];
        }

        return [
            'kind' => 'ready',
            'message' => 'Screen payload resolved successfully.',
        ];
    }
}
