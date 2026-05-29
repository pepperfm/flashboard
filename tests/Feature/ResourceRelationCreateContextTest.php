<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Illuminate\Contracts\Auth\Factory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\Route;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidationFactory;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Extensions\ExtensionRegistry;
use Pepperfm\Flashboard\Core\Forms\Builders\Form;
use Pepperfm\Flashboard\Core\Hooks\RuntimeHookDispatcher;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Core\Relations\HasMany;
use Pepperfm\Flashboard\Core\Resources\ResourceSurfaceResolver;
use Pepperfm\Flashboard\Core\Runtime\Assemblers\FormPayloadAssembler;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PolicyBridge;
use Pepperfm\Flashboard\Integration\Laravel\DataSources\ResourceFormDataSource;
use Pepperfm\Flashboard\Integration\Laravel\DataSources\ResourceRelationRecordsDataSource;
use Pepperfm\Flashboard\Integration\Laravel\Http\Controllers\ResourceFormController;
use Pepperfm\Flashboard\Integration\Laravel\Persistence\ResourceFormPersister;
use Pepperfm\Flashboard\Tests\Fixtures\Models\RelationManagerOrder;
use Pepperfm\Flashboard\Tests\Fixtures\Models\RelationManagerOrderItem;
use Pepperfm\Flashboard\Tests\Fixtures\Models\RelationManagerProfile;
use Pepperfm\Flashboard\Tests\Fixtures\Resources\RelationManagerOrderItemResource;
use Pepperfm\Flashboard\Tests\Fixtures\Resources\RelationManagerOrderResource;
use Pepperfm\Flashboard\Tests\Fixtures\Resources\RelationManagerProfileResource;
use Pepperfm\Flashboard\Tests\TestCase;

final class ResourceRelationCreateContextTest extends TestCase
{
    private Capsule $database;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bindUrlGenerator();
        $this->bindRedirector();
        $this->bindValidator();
        $this->createTables();
    }

    public function test_create_form_state_is_prefilled_from_server_resolved_relation_context(): void
    {
        $order = RelationManagerOrder::query()->create(['name' => 'Order 1']);
        $request = \Illuminate\Http\Request::create('/admin/resources/relation-manager-order-item/create', 'GET', [
            'parent_resource' => RelationManagerOrderResource::key(),
            'parent_record' => $order->getKey(),
            'relation' => 'items',
        ]);

        $payload = $this->formDataSource()->resolve(RelationManagerOrderItemResource::class, null, $request);

        self::assertSame((string) $order->getKey(), (string) $payload['state']['order_id']);
        self::assertStringContainsString('parent_resource=relation_manager_order', $payload['submit']['url']);
        self::assertStringContainsString('relation=items', $payload['submit']['url']);
        self::assertSame(
            '/flashboard.resources.relation_manager_order.edit?record=' . $order->getKey(),
            $payload['cancel']['url'],
        );
    }

    public function test_related_create_submission_overwrites_tampered_foreign_key(): void
    {
        $order = RelationManagerOrder::query()->create(['name' => 'Order 1']);
        $otherOrder = RelationManagerOrder::query()->create(['name' => 'Order 2']);
        $request = $this->storeRequest([
            'name' => 'Nested item',
            'sku' => 'N-1',
            'order_id' => $otherOrder->getKey(),
            'parent_resource' => RelationManagerOrderResource::key(),
            'parent_record' => $order->getKey(),
            'relation' => 'items',
        ]);

        $response = $this->controller()->store($request);
        $record = RelationManagerOrderItem::query()->where('name', 'Nested item')->firstOrFail();

        self::assertSame((string) $order->getKey(), (string) $record->getAttribute('order_id'));
        self::assertSame(302, $response->getStatusCode());
        self::assertSame(
            '/flashboard.resources.relation_manager_order.edit',
            $response->headers->get('location'),
        );
    }

    public function test_missing_parent_record_fails_closed(): void
    {
        $request = \Illuminate\Http\Request::create('/admin/resources/relation-manager-order-item/create', 'GET', [
            'parent_resource' => RelationManagerOrderResource::key(),
            'parent_record' => 999,
            'relation' => 'items',
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        $this->formDataSource()->resolve(RelationManagerOrderItemResource::class, null, $request);
    }

    public function test_inaccessible_parent_context_fails_closed(): void
    {
        $order = RelationManagerOrder::query()->create(['name' => 'Order 1']);
        $request = \Illuminate\Http\Request::create('/admin/resources/relation-manager-order-item/create', 'GET', [
            'parent_resource' => InaccessibleParentRelationManagerOrderResource::key(),
            'parent_record' => $order->getKey(),
            'relation' => 'items',
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class);

        $this->formDataSource($this->inaccessibleParentRegistry())->resolve(
            RelationManagerOrderItemResource::class,
            null,
            $request,
        );
    }

    public function test_create_action_is_hidden_when_related_resource_has_no_form_surface(): void
    {
        $order = RelationManagerOrder::query()->create(['name' => 'Order 1']);

        $payloads = $this->recordsDataSource($this->noFormRegistry())->initialPayloads(
            NoFormRelationManagerOrderResource::class,
            $order,
            null,
        );

        self::assertSame(['attach', 'detach'], array_column($payloads[0]['actions'], 'key'));
    }

    private function formDataSource(?ResourceRegistry $registry = null): ResourceFormDataSource
    {
        $screenAccessResolver = new ScreenAccessResolver(new PolicyBridge());

        return new ResourceFormDataSource(
            new FormPayloadAssembler(),
            $screenAccessResolver,
            $this->authenticator(),
            new ExtensionRegistry(),
            new ResourceSurfaceResolver($screenAccessResolver),
            $registry ?? $this->registry(),
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

    private function controller(): ResourceFormController
    {
        $screenAccessResolver = new ScreenAccessResolver(new PolicyBridge());

        return new ResourceFormController(
            new ResourceFormPersister(new RuntimeHookDispatcher()),
            $this->authenticator(),
            $screenAccessResolver,
            new ResourceSurfaceResolver($screenAccessResolver),
            $this->registry(),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function storeRequest(array $payload): \Illuminate\Http\Request
    {
        $request = \Illuminate\Http\Request::create('/admin/resources/relation-manager-order-item', 'POST', $payload);
        $route = new Route(['POST'], '/admin/resources/relation-manager-order-item', []);
        $route->defaults('flashboard.resource', RelationManagerOrderItemResource::class);
        $route->bind($request);
        $request->setRouteResolver(static fn(): Route => $route);

        return $request;
    }

    private function registry(): ResourceRegistry
    {
        $registry = new ResourceRegistry();
        $registry->register(RelationManagerOrderResource::class);
        $registry->register(RelationManagerProfileResource::class);
        $registry->register(RelationManagerOrderItemResource::class);

        return $registry;
    }

    private function inaccessibleParentRegistry(): ResourceRegistry
    {
        $registry = new ResourceRegistry();
        $registry->register(InaccessibleParentRelationManagerOrderResource::class);
        $registry->register(RelationManagerOrderItemResource::class);

        return $registry;
    }

    private function noFormRegistry(): ResourceRegistry
    {
        $registry = new ResourceRegistry();
        $registry->register(NoFormRelationManagerOrderResource::class);
        $registry->register(NoFormRelationManagerOrderItemResource::class);

        return $registry;
    }

    private function bindUrlGenerator(): void
    {
        $this->app->instance('url', new class()
        {
            public function route(string $name, array $parameters = [], bool $absolute = true): string
            {
                $query = $parameters === [] ? '' : '?' . http_build_query($parameters);

                return '/' . ltrim($name, '/') . $query;
            }
        });
    }

    private function bindRedirector(): void
    {
        $this->app->instance('redirect', new class() extends Redirector
        {
            public function __construct()
            {
            }

            public function route($route, $parameters = [], $status = 302, $headers = []): RedirectResponse
            {
                return new class('/' . ltrim((string) $route, '/'), $status, $headers) extends RedirectResponse
                {
                    public function with($key, $value = null)
                    {
                        return $this;
                    }
                };
            }
        });
    }

    private function bindValidator(): void
    {
        $this->app->instance('validator', new ValidationFactory(
            new Translator(new ArrayLoader(), 'en'),
            $this->app,
        ));

        if (!\Illuminate\Http\Request::hasMacro('validate')) {
            \Illuminate\Http\Request::macro('validate', function (array $rules): array {
                /** @var \Illuminate\Http\Request $this */
                return app('validator')->make($this->all(), $rules)->validate();
            });
        }
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
        $this->database->schema()->create('relation_manager_profiles', static function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('order_id')->nullable();
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

final class InaccessibleParentRelationManagerOrderResource extends Resource
{
    public static function model(): string
    {
        return RelationManagerOrder::class;
    }

    public static function key(): string
    {
        return 'inaccessible_parent_order';
    }

    public static function canAccess(?\Illuminate\Contracts\Auth\Authenticatable $user = null): bool
    {
        return false;
    }

    public static function relations(): array
    {
        return [
            HasMany::make('items', 'Items')
                ->resource(RelationManagerOrderItemResource::class),
        ];
    }
}

final class NoFormRelationManagerOrderResource extends Resource
{
    public static function model(): string
    {
        return RelationManagerOrder::class;
    }

    public static function relations(): array
    {
        return [
            HasMany::make('items', 'Items')
                ->resource(NoFormRelationManagerOrderItemResource::class)
                ->attachable()
                ->detachable(),
        ];
    }
}

final class NoFormRelationManagerOrderItemResource extends Resource
{
    public static function model(): string
    {
        return RelationManagerOrderItem::class;
    }

    public static function form(\Pepperfm\Flashboard\Contracts\Forms\FormContract $form): \Pepperfm\Flashboard\Contracts\Forms\FormContract
    {
        return Form::make();
    }
}
