<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Integration;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Integration\Laravel\FlashboardServiceProvider;
use Pepperfm\Flashboard\Integration\Laravel\Routing\PanelRouteRegistrar;
use Pepperfm\Flashboard\Tests\Fixtures\Models\AiProbeRecord;
use Pepperfm\Flashboard\Tests\Fixtures\Resources\ValidationOverrideProbeResource;
use Pepperfm\Flashboard\Tests\Fixtures\Resources\ValidationProbeResource;

final class FormValidationSerializationHttpTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [FlashboardServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('session.driver', 'array');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('ai_probe_records', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->integer('state')->default(0);
            $table->date('occurred_on');
            $table->timestamps();
        });
        Gate::define('accessFlashboard', static fn (GenericUser $user): bool => true);
        $this->actingAs(new GenericUser(['id' => 1]));
        $registry = $this->app->make(ResourceRegistry::class);
        $registry->register(ValidationProbeResource::class);
        $registry->register(ValidationOverrideProbeResource::class);
        $this->app->make(\Pepperfm\Flashboard\Core\Registry\PageRegistry::class)->register(\Pepperfm\Flashboard\Tests\Fixtures\Components\ValidationMetadataPage::class);
        $this->app->make(PanelRouteRegistrar::class)->register();
    }

    public function test_html_inertia_and_api_never_expose_server_validation_rules(): void
    {
        ValidationOverrideProbeResource::$ruleCalls = 0;
        $record = AiProbeRecord::query()->create(['name' => 'Original', 'occurred_on' => '2026-11-26']);

        foreach (['validation_probe', 'validation_override_probe'] as $resource) {
            $base = '/admin/resources/'.$resource;
            foreach ([$base.'/create', $base.'/'.$record->getKey().'/edit'] as $url) {
                $this->get($url)->assertOk()->assertDontSee('server-only-rule-configuration')
                    ->assertDontSee('server-only-denied-name');
                $this->get($url, ['X-Inertia' => 'true', 'Accept' => 'text/html'])
                    ->assertOk()->assertHeader('X-Inertia', 'true')
                    ->assertJsonPath('props.payload.form.rules', [])
                    ->assertJsonMissingPath('props.payload.form.schema.0.rules')
                    ->assertJsonMissingPath('props.payload.form.fields.0.rules')
                    ->assertDontSee('server-only-rule-configuration')->assertDontSee('server-only-denied-name');
                $this->getJson($url)->assertOk()->assertJsonPath('payload.form.rules', [])
                    ->assertJsonMissingPath('payload.form.schema.0.rules')
                    ->assertJsonMissingPath('payload.form.fields.0.rules')
                    ->assertDontSee('server-only-rule-configuration')->assertDontSee('server-only-denied-name');
            }
        }
        self::assertSame(0, ValidationOverrideProbeResource::$ruleCalls);
    }

    public function test_custom_page_can_publish_safe_form_metadata_in_component_and_workspace(): void
    {
        $url = '/admin/validation-metadata';
        $this->get($url)->assertOk()->assertDontSee('server-only-rule-configuration');
        $this->get($url, ['X-Inertia' => 'true', 'Accept' => 'text/html'])
            ->assertOk()->assertJsonPath('props.payload.component.props.form.rules', [])
            ->assertJsonPath('props.payload.workspace.form.rules', [])
            ->assertDontSee('server-only-rule-configuration');
        $this->getJson($url)->assertOk()
            ->assertJsonPath('payload.component.props.form.rules', [])
            ->assertJsonPath('payload.workspace.form.rules', [])
            ->assertDontSee('server-only-denied-name');
    }

    public function test_create_and_update_still_execute_all_rule_types(): void
    {
        foreach (['validation_probe', 'validation_override_probe'] as $resource) {
            $base = '/admin/resources/'.$resource;
            $payload = ['name' => 'Valid '.$resource, 'occurred_on' => '2026-11-26'];
            foreach (['reserved', 'class-denied', 'server-only-denied-name'] as $name) {
                $this->postJson($base, [...$payload, 'name' => $name])
                    ->assertUnprocessable()->assertJsonValidationErrors('name');
                $this->assertDatabaseMissing('ai_probe_records', ['name' => $name]);
            }

            $this->postJson($base, $payload)->assertRedirect();
            $record = AiProbeRecord::query()->where('name', $payload['name'])->firstOrFail();
            $url = $base.'/'.$record->getKey();
            if ($resource === 'validation_override_probe') {
                $this->postJson($base, $payload)->assertUnprocessable()->assertJsonValidationErrors('name');
                $this->putJson($url, $payload)->assertRedirect();
            }
            foreach (['reserved', 'class-denied', 'server-only-denied-name'] as $name) {
                $this->putJson($url, [...$payload, 'name' => $name])
                    ->assertUnprocessable()->assertJsonValidationErrors('name');
                self::assertSame($payload['name'], $record->refresh()->getAttribute('name'));
            }
            $this->putJson($url, [...$payload, 'name' => 'Updated '.$resource])->assertRedirect();
            self::assertSame('Updated '.$resource, $record->refresh()->getAttribute('name'));
        }
    }
}
