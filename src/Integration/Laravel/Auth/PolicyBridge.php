<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Auth;

use Illuminate\Database\Eloquent\Model;
use Pepperfm\Flashboard\Contracts\Resources\Resource;

final class PolicyBridge
{
    /**
     * @param class-string<Resource> $resourceClass
     */
    public function allows(
        string $resourceClass,
        string $ability,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
        ?Model $record = null,
    ): bool {
        $policyClass = $resourceClass::policy();
        if ($policyClass === null) {
            return true;
        }

        $target = $record ? $record::class : $resourceClass::model();

        return app(\Illuminate\Contracts\Auth\Access\Gate::class)
            ->forUser($user)
            ->allows($ability, $target);
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function canViewAny(string $resourceClass, ?\Illuminate\Contracts\Auth\Authenticatable $user): bool
    {
        return $this->allows($resourceClass, 'viewAny', $user);
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function canView(string $resourceClass, ?\Illuminate\Contracts\Auth\Authenticatable $user, ?Model $record): bool
    {
        if ($record === null) {
            return true;
        }

        return $this->allows($resourceClass, 'view', $user, $record);
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function canCreate(string $resourceClass, ?\Illuminate\Contracts\Auth\Authenticatable $user): bool
    {
        return $this->allows($resourceClass, 'create', $user);
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function canUpdate(string $resourceClass, ?\Illuminate\Contracts\Auth\Authenticatable $user, ?Model $record): bool
    {
        if ($record === null) {
            return true;
        }

        return $this->allows($resourceClass, 'update', $user, $record);
    }
}
