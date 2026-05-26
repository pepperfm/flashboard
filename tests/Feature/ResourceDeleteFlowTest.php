<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\Route;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Hooks\RuntimeHookDispatcher;
use Pepperfm\Flashboard\Core\Resources\ResourceSurfaceResolver;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PolicyBridge;
use Pepperfm\Flashboard\Integration\Laravel\Http\Controllers\ResourceFormController;
use Pepperfm\Flashboard\Integration\Laravel\Persistence\ResourceFormPersister;
use Pepperfm\Flashboard\Tests\Fixtures\Models\LazyFilterOptionRecord;
use Pepperfm\Flashboard\Tests\TestCase;

final class ResourceDeleteFlowTest extends TestCase
{
    private Capsule $database;

    protected function setUp(): void
    {
        parent::setUp();

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
                        $messages = is_array($key) ? $key : [$key => $value];

                        foreach ($messages as $name => $message) {
                            if (is_scalar($message) || $message === null) {
                                $this->headers->set('X-Flashboard-Flash-' . $name, (string) $message);
                            }
                        }

                        return $this;
                    }
                };
            }
        });

        $this->database = new Capsule();
        $this->database->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $this->database->setAsGlobal();
        $this->database->bootEloquent();
        $this->database->connection()->statement('PRAGMA foreign_keys = ON');
        $this->database->schema()->create('lazy_filter_option_records', static function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->increments('id');
            $table->string('status');
            $table->string('status_label');
        });
    }

    public function test_authorized_destroy_deletes_record_and_redirects_to_index(): void
    {
        $record = LazyFilterOptionRecord::query()->create([
            'status' => 'draft',
            'status_label' => 'Draft',
        ]);

        $response = $this->controller()->destroy($this->deleteRequest($this->deleteResourceClass(), $record->getKey()));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/flashboard.resources.delete_flow_records.index', $response->headers->get('location'));
        self::assertSame('Record deleted successfully.', $response->headers->get('X-Flashboard-Flash-success'));
        self::assertSame(0, LazyFilterOptionRecord::query()->count());
    }

    public function test_destroy_redirects_with_error_when_database_constraints_block_delete(): void
    {
        $record = LazyFilterOptionRecord::query()->create([
            'status' => 'draft',
            'status_label' => 'Draft',
        ]);

        $this->database->schema()->create('lazy_filter_option_record_references', static function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('lazy_filter_option_record_id');
            $table->foreign('lazy_filter_option_record_id')
                ->references('id')
                ->on('lazy_filter_option_records')
                ->restrictOnDelete();
        });

        $this->database->connection()->table('lazy_filter_option_record_references')->insert([
            'lazy_filter_option_record_id' => $record->getKey(),
        ]);

        $response = $this->controller()->destroy($this->deleteRequest($this->deleteResourceClass(), $record->getKey()));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/flashboard.resources.delete_flow_records.index', $response->headers->get('location'));
        self::assertSame(
            'Record could not be deleted because related records still reference it.',
            $response->headers->get('X-Flashboard-Flash-error'),
        );
        self::assertSame(1, LazyFilterOptionRecord::query()->count());
    }

    public function test_destroy_aborts_when_delete_policy_denies_record(): void
    {
        $this->bindGateDenying('delete');
        $record = LazyFilterOptionRecord::query()->create([
            'status' => 'draft',
            'status_label' => 'Draft',
        ]);

        try {
            $this->controller()->destroy($this->deleteRequest($this->policyDeleteResourceClass(), $record->getKey()));
            self::fail('Delete should abort when the delete policy denies the record.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
            self::assertSame(1, LazyFilterOptionRecord::query()->count());
        }
    }

    public function test_delete_policy_receives_record_instance(): void
    {
        $record = LazyFilterOptionRecord::query()->create([
            'status' => 'draft',
            'status_label' => 'Draft',
        ]);
        $this->bindGateAllowingOnlyExpectedRecord($record);

        $allowed = (new PolicyBridge())->canDelete($this->policyDeleteResourceClass(), null, $record);

        self::assertTrue($allowed);
    }

    private function controller(): ResourceFormController
    {
        $screenAccessResolver = new ScreenAccessResolver(new PolicyBridge());

        return new ResourceFormController(
            new ResourceFormPersister(new RuntimeHookDispatcher()),
            $this->authenticator(),
            $screenAccessResolver,
            new ResourceSurfaceResolver($screenAccessResolver),
        );
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    private function deleteRequest(string $resourceClass, mixed $recordKey): \Illuminate\Http\Request
    {
        $request = \Illuminate\Http\Request::create('/admin/resources/delete-flow/' . $recordKey, 'DELETE');
        $route = new Route(['DELETE'], '/admin/resources/delete-flow/{record}', []);
        $route->defaults('flashboard.resource', $resourceClass);
        $route->bind($request);
        $route->setParameter('record', $recordKey);
        $request->setRouteResolver(static fn(): Route => $route);

        return $request;
    }

    /**
     * @return class-string<Resource>
     */
    private function deleteResourceClass(): string
    {
        return get_class(new class extends Resource
        {
            public static function model(): string
            {
                return LazyFilterOptionRecord::class;
            }

            public static function key(): string
            {
                return 'delete_flow_records';
            }
        });
    }

    /**
     * @return class-string<Resource>
     */
    private function policyDeleteResourceClass(): string
    {
        return get_class(new class extends Resource
        {
            public static function model(): string
            {
                return LazyFilterOptionRecord::class;
            }

            public static function key(): string
            {
                return 'policy_delete_flow_records';
            }

            public static function policy(): string
            {
                return \stdClass::class;
            }
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

    private function bindGateAllowingOnlyExpectedRecord(LazyFilterOptionRecord $expectedRecord): void
    {
        $this->app->instance(Gate::class, new class($expectedRecord) implements Gate
        {
            public function __construct(private readonly LazyFilterOptionRecord $expectedRecord)
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
                return $ability === 'delete' && $arguments === $this->expectedRecord;
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
}
