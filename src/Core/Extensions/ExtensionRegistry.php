<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Extensions;

use Pepperfm\Flashboard\Contracts\Extensions\ActionExtensionContract;
use Pepperfm\Flashboard\Contracts\Extensions\PayloadExtensionContract;
use Pepperfm\Flashboard\Contracts\Extensions\QueryExtensionContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;

final class ExtensionRegistry
{
    /**
     * @param class-string<Resource> $resourceClass
     */
    public function extendQuery(
        string $resourceClass,
        \Illuminate\Database\Eloquent\Builder $query
    ): \Illuminate\Database\Eloquent\Builder {
        foreach ($resourceClass::queryExtensions() as $extension) {
            if ($extension instanceof QueryExtensionContract) {
                $query = $extension->extend($query);
            }
        }

        return $query;
    }

    /**
     * @param class-string<Resource> $resourceClass
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function extendPayload(string $resourceClass, string $screenPage, array $payload): array
    {
        foreach ($resourceClass::payloadExtensions() as $extension) {
            if ($extension instanceof PayloadExtensionContract) {
                $payload = $extension->extend($resourceClass, $screenPage, $payload);
            }
        }

        return $payload;
    }

    /**
     * @param class-string<Resource> $resourceClass
     * @param list<array<string, mixed>> $actions
     *
     * @return list<array<string, mixed>>
     */
    public function extendActions(string $resourceClass, array $actions): array
    {
        return array_values(array_map(static function (array $action) use ($resourceClass): array {
            foreach ($resourceClass::actionExtensions() as $extension) {
                if ($extension instanceof ActionExtensionContract) {
                    $action = $extension->extend($action);
                }
            }

            return $action;
        }, $actions));
    }
}
