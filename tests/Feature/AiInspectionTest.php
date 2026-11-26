<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Pepperfm\Flashboard\Ai\Inspection\CapabilityCatalog;
use Pepperfm\Flashboard\Ai\Inspection\RegisteredResourceLocator;
use Pepperfm\Flashboard\Ai\Inspection\ResourceInspector;
use Pepperfm\Flashboard\Contracts\Actions\ActionScope;
use Pepperfm\Flashboard\Contracts\Forms\FormContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Actions\Builders\Action;
use Pepperfm\Flashboard\Core\Forms\Fields\Select;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Tests\Fixtures\Models\BelongsToProduct;
use Pepperfm\Flashboard\Tests\TestCase;

final class AiInspectionTest extends TestCase
{
    public function test_inspection_projects_structure_without_values_or_action_execution(): void
    {
        $resource = (new class extends Resource {
            public static function model(): string
            {
                return BelongsToProduct::class;
            }

            public static function key(): string
            {
                return 'inspection_probe';
            }

            public static function form(FormContract $form): FormContract
            {
                return $form->schema([
                    Select::make('status')->options(['private' => 'private-option-sentinel']),
                ])->defaults(['status' => 'private-default-sentinel'])
                    ->rules(['status' => ['in:private-rule-sentinel']]);
            }

            public static function actions(): array
            {
                return [
                    Action::make('review')
                        ->scope(ActionScope::Record)
                        ->ability('review')
                        ->form(static function (): never {
                            throw new \LogicException('Form callback must not execute.');
                        })
                        ->visibleWhen(static function (): never {
                            throw new \LogicException('Visibility callback must not execute.');
                        })
                        ->handleUsing(static function (): never {
                            throw new \LogicException('Handler must not execute.');
                        }),
                ];
            }
        })::class;

        $registry = new ResourceRegistry();
        $registry->register($resource);
        $inspector = new ResourceInspector(new RegisteredResourceLocator($registry));

        $result = $inspector->inspect('inspection_probe');
        self::assertSame($resource, $result['class']);
        self::assertSame('status', $result['fields'][0]['key']);
        self::assertSame('record', $result['actions'][0]['scope']);
        self::assertTrue($result['actions'][0]['has_form']);
        self::assertSame('not_evaluated', $result['runtime_authorization']);
        self::assertStringNotContainsString('sentinel', json_encode($result, JSON_THROW_ON_ERROR));
    }

    public function test_unregistered_class_is_not_inspected(): void
    {
        $inspector = new ResourceInspector(new RegisteredResourceLocator(new ResourceRegistry()));

        $this->expectException(\InvalidArgumentException::class);
        $inspector->inspect(\stdClass::class);
    }

    public function test_capabilities_distinguish_manual_dsl_from_generator_support(): void
    {
        $capabilities = (new CapabilityCatalog())->describe();

        self::assertNotSame('', $capabilities['package_version']);
        self::assertSame('stdio', $capabilities['transport']);
        self::assertSame(['count', 'trend', 'breakdown'], $capabilities['features']['generation']['metrics']);
        self::assertSame(['count', 'trend', 'breakdown'], $capabilities['features']['manual_dsl']['metrics']);
        self::assertFalse($capabilities['write_safety']['business_record_access']);
    }
}
