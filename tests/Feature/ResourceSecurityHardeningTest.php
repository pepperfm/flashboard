<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Pepperfm\Flashboard\Contracts\Extensions\QueryExtensionContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Extensions\ExtensionRegistry;
use Pepperfm\Flashboard\Core\Hooks\RuntimeHookDispatcher;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Core\Relations\RelationPayloadFactory;
use Pepperfm\Flashboard\Core\Resources\ResourceSurfaceResolver;
use Pepperfm\Flashboard\Core\Runtime\Assemblers\DetailPayloadAssembler;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PolicyBridge;
use Pepperfm\Flashboard\Integration\Laravel\DataSources\ResourceDetailDataSource;
use Pepperfm\Flashboard\Integration\Laravel\DataSources\ResourceRelationRecordsDataSource;
use Pepperfm\Flashboard\Integration\Laravel\Http\Controllers\ResourceFormController;
use Pepperfm\Flashboard\Integration\Laravel\Persistence\ResourceFormPersister;
use Pepperfm\Flashboard\Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ResourceSecurityHardeningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $db = new Capsule();
        $db->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $db->setAsGlobal();
        $db->bootEloquent();
        $db->schema()->create('security_records', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('status');
            $table->string('secret');
        });

        $gate = $this->createMock(Gate::class);
        $gate->method('getPolicyFor')->willReturn(new \stdClass());
        $gate->method('forUser')->willReturnSelf();
        $gate->method('allows')->willReturnCallback(
            static fn (string $ability): bool => $ability !== 'view-hidden',
        );
        $this->app->instance(Gate::class, $gate);
        $this->app->instance('url', new class()
        {
            public function route(string $name, array $parameters = [], bool $absolute = true): string
            {
                return '/' . $name;
            }
        });
    }

    public function test_direct_record_lookup_respects_resource_query_extensions(): void
    {
        $visible = SecurityRecord::query()->create(['status' => 'visible', 'secret' => 'open']);
        $hidden = SecurityRecord::query()->create(['status' => 'hidden', 'secret' => 'private']);

        self::assertSame($visible->getKey(), SecurityResource::resolveRecord($visible->getKey())?->getKey());
        self::assertNull(SecurityResource::resolveRecord($hidden->getKey()));
        self::assertSame(2, SecurityRecord::query()->count());
    }

    public function test_detail_payload_never_serializes_unconfigured_model_attributes(): void
    {
        $record = SecurityRecord::query()->create(['status' => 'visible', 'secret' => 'private']);

        $access = new ScreenAccessResolver(new PolicyBridge());
        $authenticator = $this->authenticator();
        $datasource = new ResourceDetailDataSource(
            new DetailPayloadAssembler(),
            $access,
            $authenticator,
            new RelationPayloadFactory(),
            new ResourceRelationRecordsDataSource(
                $authenticator,
                $access,
                new ExtensionRegistry(),
                new ResourceRegistry(),
                new ResourceSurfaceResolver($access),
            ),
            new ExtensionRegistry(),
        );

        $payload = $datasource->resolve(SecurityResource::class, $record);

        self::assertSame(['id' => $record->getKey()], $payload['record']);
        self::assertArrayNotHasKey('secret', $payload['record']);
    }

    public function test_hidden_fields_cannot_be_created_through_direct_form_post(): void
    {
        $request = $this->resourceRequest('POST', null, ['status' => 'visible', 'secret' => 'privileged']);

        try {
            $this->controller()->store($request);
            self::fail('An unauthorized field must reject the HTTP request.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
        }

        self::assertSame(0, SecurityRecord::query()->count());
    }

    public function test_hidden_fields_cannot_be_overwritten_through_direct_form_patch(): void
    {
        $record = SecurityRecord::query()->create(['status' => 'visible', 'secret' => 'original']);
        $request = $this->resourceRequest('PATCH', $record->getKey(), ['secret' => 'modified']);

        try {
            $this->controller()->update($request);
            self::fail('An unauthorized field must reject the HTTP request.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
        }

        self::assertSame('original', $record->fresh()?->secret);
    }

    private function controller(): ResourceFormController
    {
        $access = new ScreenAccessResolver(new PolicyBridge());

        return new ResourceFormController(
            new ResourceFormPersister(new RuntimeHookDispatcher()),
            $this->authenticator(),
            $access,
            new ResourceSurfaceResolver($access),
        );
    }

    private function authenticator(): PanelAuthenticator
    {
        $guard = $this->createMock(StatefulGuard::class);
        $guard->method('user')->willReturn(null);
        $factory = $this->createMock(Factory::class);
        $factory->method('guard')->willReturn($guard);

        return new PanelAuthenticator($factory);
    }

    /** @param array<string, mixed> $payload */
    private function resourceRequest(string $method, mixed $recordKey, array $payload): Request
    {
        $request = Request::create('/admin/resources/security', $method, $payload);
        $route = new Route([$method], '/admin/resources/security/{record?}', []);
        $route->defaults('flashboard.resource', SecurityResource::class);
        $route->bind($request);

        if ($recordKey !== null) {
            $route->setParameter('record', $recordKey);
        }

        $request->setRouteResolver(static fn (): Route => $route);

        return $request;
    }
}

final class SecurityRecord extends Model
{
    protected $table = 'security_records';

    public $timestamps = false;

    protected $guarded = [];
}

final class SecurityScope implements QueryExtensionContract
{
    public function extend(Builder $query): Builder
    {
        return $query->where('status', 'visible');
    }
}

final class SecurityResource extends Resource
{
    public static function model(): string
    {
        return SecurityRecord::class;
    }

    public static function policy(): ?string
    {
        return \stdClass::class;
    }

    public static function fieldAbilityMap(): array
    {
        return ['secret' => 'view-hidden'];
    }

    public static function queryExtensions(): array
    {
        return [new SecurityScope()];
    }
}
