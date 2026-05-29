<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Routing\Route;
use Pepperfm\Flashboard\Contracts\Extensions\QueryExtensionContract;
use Pepperfm\Flashboard\Contracts\Forms\FormContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Extensions\ExtensionRegistry;
use Pepperfm\Flashboard\Core\Forms\Fields\TextInput;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Core\Relations\HasMany;
use Pepperfm\Flashboard\Core\Resources\ResourceSurfaceResolver;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PolicyBridge;
use Pepperfm\Flashboard\Integration\Laravel\DataSources\ResourceRelationAttachOptionsDataSource;
use Pepperfm\Flashboard\Integration\Laravel\DataSources\ResourceRelationRecordsDataSource;
use Pepperfm\Flashboard\Integration\Laravel\Http\Controllers\ResourceRelationRecordsController;
use Pepperfm\Flashboard\Integration\Laravel\Persistence\ResourceRelationManagerPersister;
use Pepperfm\Flashboard\Tests\Fixtures\Models\RelationManagerOrder;
use Pepperfm\Flashboard\Tests\Fixtures\Models\RelationManagerOrderItem;
use Pepperfm\Flashboard\Tests\TestCase;

final class ResourceRelationAuthorizationTest extends TestCase
{
    private Capsule $database;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bindUrlGenerator();
        $this->createTables();
    }

    public function test_hidden_relation_is_not_loaded(): void
    {
        $this->bindGateDenying('view-hidden-relation');
        $order = RelationManagerOrder::query()->create(['name' => 'Order 1']);

        $payloads = $this->recordsDataSource($this->hiddenRelationRegistry())->initialPayloads(
            HiddenRelationManagerOrderResource::class,
            $order,
            null,
        );

        self::assertSame([], $payloads);
    }

    public function test_inaccessible_parent_resource_is_rejected_at_http_boundary(): void
    {
        $order = RelationManagerOrder::query()->create(['name' => 'Order 1']);
        $request = \Illuminate\Http\Request::create('/admin/resources/orders/' . $order->getKey() . '/_relations/items', 'GET');
        $route = new Route(['GET'], '/admin/resources/orders/{record}/_relations/{relation}', []);
        $route->defaults('flashboard.resource', InaccessibleRelationManagerOrderResource::class);
        $route->bind($request);
        $route->setParameter('record', $order->getKey());
        $request->setRouteResolver(static fn(): Route => $route);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        $this->recordsController($this->inaccessibleParentRegistry())($request, 'items');
    }

    public function test_inaccessible_related_resource_cannot_load_attach_options(): void
    {
        $order = RelationManagerOrder::query()->create(['name' => 'Order 1']);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class);

        $this->attachOptionsDataSource($this->inaccessibleRelatedRegistry())->resolve(
            InaccessibleRelatedRelationManagerOrderResource::class,
            $order,
            'items',
            \Illuminate\Http\Request::create('/', 'GET'),
        );
    }

    public function test_denied_create_policy_hides_create_action(): void
    {
        $this->bindGateDenying('create');
        $order = RelationManagerOrder::query()->create(['name' => 'Order 1']);

        $payloads = $this->recordsDataSource($this->createDeniedRegistry())->initialPayloads(
            CreateDeniedRelationManagerOrderResource::class,
            $order,
            null,
        );

        self::assertNotContains('create', array_column($payloads[0]['actions'], 'key'));
    }

    public function test_denied_related_update_policy_blocks_attach(): void
    {
        $this->bindGateDenying('update');
        $order = RelationManagerOrder::query()->create(['name' => 'Order 1']);
        $item = RelationManagerOrderItem::query()->create([
            'order_id' => null,
            'name' => 'Detached item',
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class);

        $this->persister($this->updateDeniedRegistry())->attach(
            UpdateDeniedRelationManagerOrderResource::class,
            $order,
            'items',
            $item->getKey(),
        );
    }

    public function test_scoped_out_attach_record_fails_closed(): void
    {
        $order = RelationManagerOrder::query()->create(['name' => 'Order 1']);
        $hidden = RelationManagerOrderItem::query()->create([
            'order_id' => null,
            'name' => 'Hidden item',
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        $this->persister($this->scopedAttachRegistry())->attach(
            ScopedAttachRelationManagerOrderResource::class,
            $order,
            'items',
            $hidden->getKey(),
        );
    }

    private function recordsDataSource(ResourceRegistry $registry): ResourceRelationRecordsDataSource
    {
        $screenAccessResolver = new ScreenAccessResolver(new PolicyBridge());

        return new ResourceRelationRecordsDataSource(
            $this->authenticator(),
            $screenAccessResolver,
            new ExtensionRegistry(),
            $registry,
            new ResourceSurfaceResolver($screenAccessResolver),
        );
    }

    private function attachOptionsDataSource(ResourceRegistry $registry): ResourceRelationAttachOptionsDataSource
    {
        $screenAccessResolver = new ScreenAccessResolver(new PolicyBridge());

        return new ResourceRelationAttachOptionsDataSource(
            $this->authenticator(),
            $screenAccessResolver,
            new ExtensionRegistry(),
            $registry,
            new ResourceSurfaceResolver($screenAccessResolver),
        );
    }

    private function recordsController(ResourceRegistry $registry): ResourceRelationRecordsController
    {
        $screenAccessResolver = new ScreenAccessResolver(new PolicyBridge());

        return new ResourceRelationRecordsController(
            $this->recordsDataSource($registry),
            $this->authenticator(),
            $screenAccessResolver,
        );
    }

    private function persister(ResourceRegistry $registry): ResourceRelationManagerPersister
    {
        return new ResourceRelationManagerPersister(
            $registry,
            new ScreenAccessResolver(new PolicyBridge()),
            new ExtensionRegistry(),
        );
    }

    private function hiddenRelationRegistry(): ResourceRegistry
    {
        $registry = new ResourceRegistry();
        $registry->register(HiddenRelationManagerOrderResource::class);

        return $registry;
    }

    private function inaccessibleParentRegistry(): ResourceRegistry
    {
        $registry = new ResourceRegistry();
        $registry->register(InaccessibleRelationManagerOrderResource::class);

        return $registry;
    }

    private function inaccessibleRelatedRegistry(): ResourceRegistry
    {
        $registry = new ResourceRegistry();
        $registry->register(InaccessibleRelatedRelationManagerOrderResource::class);
        $registry->register(InaccessibleRelatedRelationManagerOrderItemResource::class);

        return $registry;
    }

    private function createDeniedRegistry(): ResourceRegistry
    {
        $registry = new ResourceRegistry();
        $registry->register(CreateDeniedRelationManagerOrderResource::class);
        $registry->register(CreateDeniedRelationManagerOrderItemResource::class);

        return $registry;
    }

    private function updateDeniedRegistry(): ResourceRegistry
    {
        $registry = new ResourceRegistry();
        $registry->register(UpdateDeniedRelationManagerOrderResource::class);
        $registry->register(UpdateDeniedRelationManagerOrderItemResource::class);

        return $registry;
    }

    private function scopedAttachRegistry(): ResourceRegistry
    {
        $registry = new ResourceRegistry();
        $registry->register(ScopedAttachRelationManagerOrderResource::class);
        $registry->register(ScopedAttachRelationManagerOrderItemResource::class);

        return $registry;
    }

    private function bindUrlGenerator(): void
    {
        $this->app->instance('url', new class()
        {
            public function route(string $name, array $parameters = [], bool $absolute = true): string
            {
                return '/' . ltrim($name, '/');
            }
        });
    }

    private function bindGateDenying(string $deniedAbility): void
    {
        $this->app->instance(Gate::class, new class($deniedAbility) implements Gate
        {
            public function __construct(private readonly string $deniedAbility)
            {
            }

            public function has($ability): bool
            {
                return true;
            }

            public function define($ability, $callback): static
            {
                return $this;
            }

            public function resource($name, $class, ?array $abilities = null): static
            {
                return $this;
            }

            public function policy($class, $policy): static
            {
                return $this;
            }

            public function before(callable $callback): static
            {
                return $this;
            }

            public function after(callable $callback): static
            {
                return $this;
            }

            public function allows($ability, $arguments = []): bool
            {
                return $ability !== $this->deniedAbility;
            }

            public function denies($ability, $arguments = []): bool
            {
                return !$this->allows($ability, $arguments);
            }

            public function check($abilities, $arguments = []): bool
            {
                foreach ((array) $abilities as $ability) {
                    if (!$this->allows($ability, $arguments)) {
                        return false;
                    }
                }

                return true;
            }

            public function any($abilities, $arguments = []): bool
            {
                foreach ((array) $abilities as $ability) {
                    if ($this->allows($ability, $arguments)) {
                        return true;
                    }
                }

                return false;
            }

            public function authorize($ability, $arguments = []): \Illuminate\Auth\Access\Response
            {
                return $this->inspect($ability, $arguments);
            }

            public function inspect($ability, $arguments = []): \Illuminate\Auth\Access\Response
            {
                return new \Illuminate\Auth\Access\Response($this->allows($ability, $arguments));
            }

            public function raw($ability, $arguments = []): bool
            {
                return $this->allows($ability, $arguments);
            }

            public function getPolicyFor($class): mixed
            {
                return null;
            }

            public function forUser($user): static
            {
                return $this;
            }

            public function abilities(): array
            {
                return [];
            }
        });
    }

    private function createTables(): void
    {
        $this->database = new Capsule();
        $this->database->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $this->database->setAsGlobal();
        $this->database->bootEloquent();
        $this->database->schema()->create('relation_manager_orders', static function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->increments('id');
            $table->string('name')->nullable();
        });
        $this->database->schema()->create('relation_manager_order_items', static function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('order_id')->nullable();
            $table->string('name')->nullable();
            $table->string('sku')->nullable();
        });
    }

    private function authenticator(): PanelAuthenticator
    {
        return new PanelAuthenticator(new class() implements Factory
        {
            public function guard($name = null): Guard|StatefulGuard
            {
                return new class() implements StatefulGuard
                {
                    public function check(): bool
                    {
                        return false;
                    }

                    public function guest(): bool
                    {
                        return true;
                    }

                    public function user(): ?\Illuminate\Contracts\Auth\Authenticatable
                    {
                        return null;
                    }

                    public function id(): int|string|null
                    {
                        return null;
                    }

                    public function validate(array $credentials = []): bool
                    {
                        return false;
                    }

                    public function hasUser(): bool
                    {
                        return false;
                    }

                    public function setUser(\Illuminate\Contracts\Auth\Authenticatable $user): static
                    {
                        return $this;
                    }

                    public function attempt(array $credentials = [], $remember = false): bool
                    {
                        return false;
                    }

                    public function once(array $credentials = []): bool
                    {
                        return false;
                    }

                    public function login(\Illuminate\Contracts\Auth\Authenticatable $user, $remember = false): void
                    {
                    }

                    public function loginUsingId($id, $remember = false): \Illuminate\Contracts\Auth\Authenticatable|false
                    {
                        return false;
                    }

                    public function onceUsingId($id): \Illuminate\Contracts\Auth\Authenticatable|false
                    {
                        return false;
                    }

                    public function viaRemember(): bool
                    {
                        return false;
                    }

                    public function logout(): void
                    {
                    }
                };
            }

            public function shouldUse($name): void
            {
            }
        });
    }
}

final class HiddenRelationManagerOrderResource extends Resource
{
    public static function model(): string
    {
        return RelationManagerOrder::class;
    }

    public static function policy(): string
    {
        return \stdClass::class;
    }

    public static function relationAbilityMap(): array
    {
        return [
            'items' => 'view-hidden-relation',
        ];
    }

    public static function relations(): array
    {
        return [
            HasMany::make('items', 'Items')
                ->model(RelationManagerOrderItem::class),
        ];
    }
}

final class InaccessibleRelationManagerOrderResource extends Resource
{
    public static function model(): string
    {
        return RelationManagerOrder::class;
    }

    public static function canAccess(?\Illuminate\Contracts\Auth\Authenticatable $user = null): bool
    {
        return false;
    }

    public static function relations(): array
    {
        return [
            HasMany::make('items', 'Items')
                ->model(RelationManagerOrderItem::class),
        ];
    }
}

final class InaccessibleRelatedRelationManagerOrderResource extends Resource
{
    public static function model(): string
    {
        return RelationManagerOrder::class;
    }

    public static function relations(): array
    {
        return [
            HasMany::make('items', 'Items')
                ->resource(InaccessibleRelatedRelationManagerOrderItemResource::class)
                ->attachable(),
        ];
    }
}

final class InaccessibleRelatedRelationManagerOrderItemResource extends Resource
{
    public static function model(): string
    {
        return RelationManagerOrderItem::class;
    }

    public static function canAccess(?\Illuminate\Contracts\Auth\Authenticatable $user = null): bool
    {
        return false;
    }
}

final class CreateDeniedRelationManagerOrderResource extends Resource
{
    public static function model(): string
    {
        return RelationManagerOrder::class;
    }

    public static function relations(): array
    {
        return [
            HasMany::make('items', 'Items')
                ->resource(CreateDeniedRelationManagerOrderItemResource::class),
        ];
    }
}

final class CreateDeniedRelationManagerOrderItemResource extends Resource
{
    public static function model(): string
    {
        return RelationManagerOrderItem::class;
    }

    public static function policy(): string
    {
        return \stdClass::class;
    }

    public static function form(FormContract $form): FormContract
    {
        return $form->schema([
            TextInput::make('name', 'Name'),
        ]);
    }
}

final class UpdateDeniedRelationManagerOrderResource extends Resource
{
    public static function model(): string
    {
        return RelationManagerOrder::class;
    }

    public static function relations(): array
    {
        return [
            HasMany::make('items', 'Items')
                ->resource(UpdateDeniedRelationManagerOrderItemResource::class)
                ->attachable(),
        ];
    }
}

final class UpdateDeniedRelationManagerOrderItemResource extends Resource
{
    public static function model(): string
    {
        return RelationManagerOrderItem::class;
    }

    public static function policy(): string
    {
        return \stdClass::class;
    }
}

final class ScopedAttachRelationManagerOrderResource extends Resource
{
    public static function model(): string
    {
        return RelationManagerOrder::class;
    }

    public static function relations(): array
    {
        return [
            HasMany::make('items', 'Items')
                ->resource(ScopedAttachRelationManagerOrderItemResource::class)
                ->attachable(),
        ];
    }
}

final class ScopedAttachRelationManagerOrderItemResource extends Resource
{
    public static function model(): string
    {
        return RelationManagerOrderItem::class;
    }

    public static function queryExtensions(): array
    {
        return [
            new class() implements QueryExtensionContract
            {
                public function extend(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
                {
                    return $query->where('name', '!=', 'Hidden item');
                }
            },
        ];
    }
}
