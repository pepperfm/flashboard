<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Authorization;

use Pepperfm\Flashboard\Contracts\Resources\Resource;

interface ResourcePolicyGateContract
{
    /**
     * @param class-string<Resource> $resourceClass
     */
    public function allows(
        string $resourceClass,
        string $ability,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
        ?\Illuminate\Database\Eloquent\Model $record = null,
    ): bool;

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function canViewAny(string $resourceClass, ?\Illuminate\Contracts\Auth\Authenticatable $user): bool;

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function canView(
        string $resourceClass,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
        ?\Illuminate\Database\Eloquent\Model $record,
    ): bool;

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function canCreate(string $resourceClass, ?\Illuminate\Contracts\Auth\Authenticatable $user): bool;

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function canUpdate(
        string $resourceClass,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
        ?\Illuminate\Database\Eloquent\Model $record,
    ): bool;

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function canDelete(
        string $resourceClass,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
        ?\Illuminate\Database\Eloquent\Model $record,
    ): bool;
}
