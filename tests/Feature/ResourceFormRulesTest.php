<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Pepperfm\Flashboard\Contracts\Forms\FieldRenderer;
use Pepperfm\Flashboard\Contracts\Forms\FormContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Forms\Fields\Checkbox;
use Pepperfm\Flashboard\Core\Forms\Fields\NumberInput;
use Pepperfm\Flashboard\Core\Forms\Fields\TextInput;
use Pepperfm\Flashboard\Tests\TestCase;

final class ResourceFormRulesTest extends TestCase
{
    public function test_creation_and_update_rules_are_inferred_from_form_fields(): void
    {
        $resourceClass = new class() extends Resource
        {
            public static function model(): string
            {
                return \Illuminate\Database\Eloquent\Model::class;
            }

            public static function form(FormContract $form): FormContract
            {
                return $form->schema([
                    ['key' => 'name', 'label' => 'Name', 'type' => 'text'],
                ]);
            }
        };

        self::assertSame(
            ['name' => ['nullable', 'string']],
            $resourceClass::creationRules(),
        );

        $record = new class() extends \Illuminate\Database\Eloquent\Model
        {
        };

        self::assertSame(
            ['name' => ['nullable', 'string']],
            $resourceClass::updateRules($record),
        );
    }

    public function test_creation_and_update_rules_fall_back_to_form_builder_rules(): void
    {
        $resourceClass = new class() extends Resource
        {
            public static function model(): string
            {
                return \Illuminate\Database\Eloquent\Model::class;
            }

            public static function form(FormContract $form): FormContract
            {
                return $form
                    ->schema([
                        ['key' => 'email', 'label' => 'Email', 'type' => 'text', 'input_type' => 'email', 'required' => true],
                        ['key' => 'name', 'label' => 'Name', 'type' => 'text'],
                    ])
                    ->rules([
                        'email' => ['max:255'],
                    ]);
            }
        };

        self::assertSame(
            [
                'email' => ['required', 'string', 'email', 'max:255'],
                'name' => ['nullable', 'string'],
            ],
            $resourceClass::creationRules(),
        );
        $record = new class() extends \Illuminate\Database\Eloquent\Model
        {
        };
        self::assertSame(
            [
                'email' => ['required', 'string', 'email', 'max:255'],
                'name' => ['nullable', 'string'],
            ],
            $resourceClass::updateRules($record),
        );
    }

    public function test_textarea_renderer_fields_still_infer_string_rules_and_merge_explicit_rules(): void
    {
        $resourceClass = new class() extends Resource
        {
            public static function model(): string
            {
                return \Illuminate\Database\Eloquent\Model::class;
            }

            public static function form(FormContract $form): FormContract
            {
                return $form
                    ->schema([
                        TextInput::make('notes')
                            ->label('Notes')
                            ->renderer(FieldRenderer::Textarea)
                            ->required(),
                    ])
                    ->rules([
                        'notes' => ['max:500'],
                    ]);
            }
        };

        self::assertSame(
            ['notes' => ['required', 'string', 'max:500']],
            $resourceClass::creationRules(),
        );

        $record = new class() extends \Illuminate\Database\Eloquent\Model
        {
        };

        self::assertSame(
            ['notes' => ['required', 'string', 'max:500']],
            $resourceClass::updateRules($record),
        );
    }

    public function test_checkbox_and_number_fields_infer_boolean_and_numeric_rules(): void
    {
        $resourceClass = new class() extends Resource
        {
            public static function model(): string
            {
                return \Illuminate\Database\Eloquent\Model::class;
            }

            public static function form(FormContract $form): FormContract
            {
                return $form->schema([
                    Checkbox::make('is_featured')->label('Featured'),
                    NumberInput::make('sort_order')->label('Sort order')->required(),
                ]);
            }
        };

        self::assertSame(
            [
                'is_featured' => ['nullable', 'boolean'],
                'sort_order' => ['required', 'numeric'],
            ],
            $resourceClass::creationRules(),
        );
    }
}
