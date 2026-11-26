<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Authorization\Visibility;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Contracts\Actions\ActionContract;
use Pepperfm\Flashboard\Contracts\Actions\ContextualActionContract;
use Pepperfm\Flashboard\Contracts\Authorization\ResourcePolicyGateContract;
use Pepperfm\Flashboard\Contracts\Pages\PageDefinitionContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Runtime\Actions\ActionContext;

#[Singleton]
final readonly class ScreenAccessResolver
{
    public function __construct(
        private ResourcePolicyGateContract $policyGate,
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
        return $resourceClass::canAccess($user) && $this->policyGate->canViewAny($resourceClass, $user);
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
        return $resourceClass::canAccess($user) && $this->policyGate->canView($resourceClass, $user, $record);
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function canCreateRecord(string $resourceClass, ?\Illuminate\Contracts\Auth\Authenticatable $user): bool
    {
        return !$resourceClass::isReadOnly() && $resourceClass::canAccess($user) && $this->policyGate->canCreate($resourceClass, $user);
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function canEditRecord(
        string $resourceClass,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
        ?Model $record
    ): bool {
        return !$resourceClass::isReadOnly() && $resourceClass::canAccess($user) && $this->policyGate->canUpdate($resourceClass, $user, $record);
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function canDeleteRecord(
        string $resourceClass,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
        ?Model $record
    ): bool {
        return !$resourceClass::isReadOnly() && $resourceClass::canAccess($user) && $this->policyGate->canDelete($resourceClass, $user, $record);
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function canViewMetric(
        string $resourceClass,
        string $ability,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
    ): bool {
        return $this->canAccessResource($resourceClass, $user)
            && $this->policyGate->allows($resourceClass, $ability, $user);
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function canViewField(
        string $resourceClass,
        string $fieldKey,
        ?\Illuminate\Contracts\Auth\Authenticatable $user
    ): bool {
        $ability = $this->abilityForKey($resourceClass::fieldAbilityMap(), $fieldKey);

        return $ability === null || $this->policyGate->allows($resourceClass, $ability, $user);
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function canViewAction(
        string $resourceClass,
        string $actionKey,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
        ?Model $record = null,
    ): bool {
        $ability = $this->abilityForKey($resourceClass::actionAbilityMap(), $actionKey);

        return $ability === null || $this->allowsExplicitActionAbility($resourceClass, $ability, $user, $record);
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function canExecuteAction(
        string $resourceClass,
        ActionContract $action,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
        ?Model $record = null,
    ): bool {
        if ($resourceClass::isReadOnly()) {
            return false;
        }

        $allowed = $record === null
            ? $this->canAccessResource($resourceClass, $user)
            : $this->canViewRecord($resourceClass, $user, $record);

        if (!$allowed || !$this->canViewAction($resourceClass, $action->key(), $user, $record)) {
            return false;
        }
        if (!$action instanceof ContextualActionContract) {
            return (bool) Arr::get($action->toArray(), 'visible', true);
        }

        $context = new ActionContext($resourceClass, $user, $record)->toArray();
        $ability = $action->requiredAbility();

        return $action->actionScope()->acceptsRecord($record !== null)
            && ($ability === null || $this->allowsExplicitActionAbility($resourceClass, $ability, $user, $record))
            && $action->isVisible($context)
            && $action->isAuthorized($context);
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function canViewRelation(
        string $resourceClass,
        string $relationKey,
        ?\Illuminate\Contracts\Auth\Authenticatable $user
    ): bool {
        $ability = $this->abilityForKey($resourceClass::relationAbilityMap(), $relationKey);

        return $ability === null || $this->policyGate->allows($resourceClass, $ability, $user);
    }

    /** @param class-string<Resource> $resourceClass */
    private function allowsExplicitActionAbility(
        string $resourceClass,
        string $ability,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
        ?Model $record,
    ): bool {
        $policy = $resourceClass::policy();

        return $policy !== null
            && method_exists($policy, str_contains($ability, '-') ? str($ability)->camel()->value() : $ability)
            && $this->policyGate->allows($resourceClass, $ability, $user, $record);
    }

    /**
     * @param array<string, string> $map
     */
    private function abilityForKey(array $map, string $key): ?string
    {
        $normalizedMap = [];

        foreach ($map as $mapKey => $ability) {
            $normalizedMap[trim((string) $mapKey)] = $ability;
        }

        return $normalizedMap[trim($key)] ?? null;
    }
}
