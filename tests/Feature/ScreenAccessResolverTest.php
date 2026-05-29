<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Pepperfm\Flashboard\Contracts\Authorization\ResourcePolicyGateContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Tests\TestCase;

final class ScreenAccessResolverTest extends TestCase
{
    public function test_resource_access_delegates_to_policy_gate_contract(): void
    {
        $policyGate = new class implements ResourcePolicyGateContract
        {
            public bool $canViewAnyCalled = false;

            public function allows(
                string $resourceClass,
                string $ability,
                ?\Illuminate\Contracts\Auth\Authenticatable $user,
                ?\Illuminate\Database\Eloquent\Model $record = null,
            ): bool {
                return true;
            }

            public function canViewAny(string $resourceClass, ?\Illuminate\Contracts\Auth\Authenticatable $user): bool
            {
                $this->canViewAnyCalled = true;

                return true;
            }

            public function canView(
                string $resourceClass,
                ?\Illuminate\Contracts\Auth\Authenticatable $user,
                ?\Illuminate\Database\Eloquent\Model $record,
            ): bool {
                return true;
            }

            public function canCreate(string $resourceClass, ?\Illuminate\Contracts\Auth\Authenticatable $user): bool
            {
                return true;
            }

            public function canUpdate(
                string $resourceClass,
                ?\Illuminate\Contracts\Auth\Authenticatable $user,
                ?\Illuminate\Database\Eloquent\Model $record,
            ): bool {
                return true;
            }

            public function canDelete(
                string $resourceClass,
                ?\Illuminate\Contracts\Auth\Authenticatable $user,
                ?\Illuminate\Database\Eloquent\Model $record,
            ): bool {
                return true;
            }
        };
        $resourceClass = get_class(new class extends Resource
        {
            public static function model(): string
            {
                return \Illuminate\Database\Eloquent\Model::class;
            }
        });

        self::assertTrue(new ScreenAccessResolver($policyGate)->canAccessResource($resourceClass, null));
        self::assertTrue($policyGate->canViewAnyCalled);
    }
}
