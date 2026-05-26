<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\StatefulGuard;
use Pepperfm\Flashboard\Contracts\Forms\FormContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Extensions\ExtensionRegistry;
use Pepperfm\Flashboard\Core\Forms\Fields\Checkbox;
use Pepperfm\Flashboard\Core\Forms\Fields\TextInput;
use Pepperfm\Flashboard\Core\Forms\Fields\Toggle;
use Pepperfm\Flashboard\Core\Forms\Layout\Section;
use Pepperfm\Flashboard\Core\Forms\Layout\Tab;
use Pepperfm\Flashboard\Core\Forms\Layout\Tabs;
use Pepperfm\Flashboard\Core\Resources\ResourceSurfaceResolver;
use Pepperfm\Flashboard\Core\Runtime\Assemblers\FormPayloadAssembler;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PolicyBridge;
use Pepperfm\Flashboard\Integration\Laravel\DataSources\ResourceFormDataSource;
use Pepperfm\Flashboard\Tests\TestCase;

final class ResourceFormDataSourceTest extends TestCase
{
    public function test_hidden_fields_are_pruned_from_the_schema_tree_and_flattened_fields(): void
    {
        $this->fakeUrlGenerator();
        $this->fakeGateForFieldVisibility();

        $payload = $this->makeDataSource()->resolve($this->visibilityAwareResourceClass());

        self::assertSame(['email', 'is_active'], array_column($payload['fields'], 'key'));
        self::assertSame(['email'], array_column($payload['schema'][0]['schema'], 'key'));
        self::assertSame(['is_active'], array_column($payload['schema'][1]['tabs'][0]['schema'], 'key'));
    }

    public function test_boolean_fields_default_to_false_without_overriding_explicit_defaults_or_record_state(): void
    {
        $this->fakeUrlGenerator();
        $this->fakeGateForFieldVisibility();

        $resourceClass = $this->booleanDefaultsResourceClass();
        $createPayload = $this->makeDataSource()->resolve($resourceClass);

        self::assertFalse($createPayload['state']['is_featured']);
        self::assertTrue($createPayload['state']['is_active']);
        self::assertArrayNotHasKey('name', $createPayload['state']);

        $record = new class() extends \Illuminate\Database\Eloquent\Model
        {
            protected $guarded = [];
        };
        $record->forceFill([
            'is_featured' => true,
            'is_active' => false,
            'name' => 'Published product',
        ]);
        $record->exists = true;

        $editPayload = $this->makeDataSource()->resolve($resourceClass, $record);

        self::assertTrue($editPayload['state']['is_featured']);
        self::assertFalse($editPayload['state']['is_active']);
        self::assertSame('Published product', $editPayload['state']['name']);
    }

    private function fakeUrlGenerator(): void
    {
        $this->app->instance('url', new class()
        {
            public function route(string $name, array $parameters = [], bool $absolute = true): string
            {
                return '/' . ltrim($name, '/');
            }
        });
    }

    private function fakeGateForFieldVisibility(): void
    {
        $this->app->instance(Gate::class, new class() implements Gate
        {
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
                return $ability !== 'view-hidden';
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

    private function makeDataSource(): ResourceFormDataSource
    {
        return new ResourceFormDataSource(
            new FormPayloadAssembler(),
            new ScreenAccessResolver(new PolicyBridge()),
            new PanelAuthenticator(new class() implements Factory
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
            }),
            new ExtensionRegistry(),
            new ResourceSurfaceResolver(new ScreenAccessResolver(new PolicyBridge())),
        );
    }

    /**
     * @return class-string<Resource>
     */
    private function visibilityAwareResourceClass(): string
    {
        return get_class(new class() extends Resource
        {
            public static function model(): string
            {
                return \Illuminate\Database\Eloquent\Model::class;
            }

            public static function policy(): string
            {
                return \stdClass::class;
            }

            public static function fieldAbilityMap(): array
            {
                return [
                    'secret' => 'view-hidden',
                    'secret_toggle' => 'view-hidden',
                ];
            }

            public static function form(FormContract $form): FormContract
            {
                return $form->schema([
                    Section::make('main')->label('Main')->schema([
                        TextInput::make('email')->label('Email'),
                        TextInput::make('secret')->label('Secret'),
                    ]),
                    Tabs::make('settings')->tabs([
                        Tab::make('access')->label('Access')->schema([
                            TextInput::make('is_active')->label('Is active'),
                            TextInput::make('secret_toggle')->label('Hidden Toggle'),
                        ]),
                    ]),
                ]);
            }
        });
    }

    /**
     * @return class-string<Resource>
     */
    private function booleanDefaultsResourceClass(): string
    {
        return get_class(new class() extends Resource
        {
            public static function model(): string
            {
                return \Illuminate\Database\Eloquent\Model::class;
            }

            public static function form(FormContract $form): FormContract
            {
                return $form
                    ->schema([
                        TextInput::make('name')->label('Name'),
                        Checkbox::make('is_featured')->label('Featured'),
                        Toggle::make('is_active')->label('Is active'),
                    ])
                    ->defaults([
                        'is_active' => true,
                    ]);
            }
        });
    }
}
