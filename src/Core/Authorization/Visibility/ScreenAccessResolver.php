<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Authorization\Visibility;

use Illuminate\Database\Eloquent\Model;
use Pepperfm\Flashboard\Contracts\Pages\PageDefinitionContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PolicyBridge;

final readonly class ScreenAccessResolver
{
    public function __construct(
        private PolicyBridge $policyBridge,
    ) {
    }

    /**
     * @param class-string<PageDefinitionContract> $pageClass
     */
    public function canAccessPage(string $pageClass, ?\Illuminate\Contracts\Auth\Authenticatable $user): bool
    {
        return $pageClass::canAccess($user);
    }

    /**
     * @param class-string<PageDefinitionContract> $pageClass
     */
    public function canViewPageInNavigation(string $pageClass, ?\Illuminate\Contracts\Auth\Authenticatable $user): bool
    {
        return $pageClass::isNavigable() && $pageClass::canAccess($user);
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function canAccessResource(string $resourceClass, ?\Illuminate\Contracts\Auth\Authenticatable $user): bool
    {
        return $resourceClass::canAccess($user) && $this->policyBridge->canViewAny($resourceClass, $user);
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function canViewResourceInNavigation(
        string $resourceClass,
        ?\Illuminate\Contracts\Auth\Authenticatable $user
    ): bool {
        return $resourceClass::isVisibleInNavigation() && $this->canAccessResource($resourceClass, $user);
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function canViewRecord(
        string $resourceClass,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
        ?Model $record
    ): bool {
        return $resourceClass::canAccess($user) && $this->policyBridge->canView($resourceClass, $user, $record);
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function canCreateRecord(string $resourceClass, ?\Illuminate\Contracts\Auth\Authenticatable $user): bool
    {
        return $resourceClass::canAccess($user) && $this->policyBridge->canCreate($resourceClass, $user);
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function canEditRecord(
        string $resourceClass,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
        ?Model $record
    ): bool {
        return $resourceClass::canAccess($user) && $this->policyBridge->canUpdate($resourceClass, $user, $record);
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function canViewField(
        string $resourceClass,
        string $fieldKey,
        ?\Illuminate\Contracts\Auth\Authenticatable $user
    ): bool {
        $ability = $resourceClass::fieldAbilityMap()[$fieldKey] ?? null;

        return $ability === null || $this->policyBridge->allows($resourceClass, $ability, $user);
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function canViewAction(
        string $resourceClass,
        string $actionKey,
        ?\Illuminate\Contracts\Auth\Authenticatable $user
    ): bool {
        $ability = $resourceClass::actionAbilityMap()[$actionKey] ?? null;

        return $ability === null || $this->policyBridge->allows($resourceClass, $ability, $user);
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function canViewRelation(
        string $resourceClass,
        string $relationKey,
        ?\Illuminate\Contracts\Auth\Authenticatable $user
    ): bool {
        $ability = $resourceClass::relationAbilityMap()[$relationKey] ?? null;

        return $ability === null || $this->policyBridge->allows($resourceClass, $ability, $user);
    }
}
