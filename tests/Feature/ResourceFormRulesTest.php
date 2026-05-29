<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Pepperfm\Flashboard\Contracts\Forms\FieldRenderer;
use Pepperfm\Flashboard\Contracts\Forms\FormContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Forms\Fields\BelongsTo;
use Pepperfm\Flashboard\Core\Forms\Fields\Checkbox;
use Pepperfm\Flashboard\Core\Forms\Fields\DateInput;
use Pepperfm\Flashboard\Core\Forms\Fields\FileUpload;
use Pepperfm\Flashboard\Core\Forms\Fields\NumberInput;
use Pepperfm\Flashboard\Core\Forms\Fields\PasswordInput;
use Pepperfm\Flashboard\Core\Forms\Fields\RichText;
use Pepperfm\Flashboard\Core\Forms\Fields\TextInput;
use Pepperfm\Flashboard\Tests\Fixtures\Models\BelongsToCategory;
use Pepperfm\Flashboard\Tests\Fixtures\Resources\BelongsToProductResource;
use Pepperfm\Flashboard\Tests\TestCase;

final class ResourceFormRulesTest extends TestCase
{
    public function test_creation_and_update_rules_are_inferred_from_form_fields(): void
    {
        $resourceClass = new class() extends Resource
        {
            public static function model(): string
            {
                return ResourceFormRulesGeneratedKeyModel::class;
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

    public function test_creation_rules_ignore_generated_primary_key_rules(): void
    {
        $resourceClass = new class() extends Resource
        {
            public static function model(): string
            {
                return ResourceFormRulesGeneratedKeyModel::class;
            }

            public static function form(FormContract $form): FormContract
            {
                return $form->schema([
                    TextInput::make('id')->label('ID')->required(),
                    TextInput::make('name')->label('Name')->required(),
                ]);
            }
        };

        self::assertSame(
            ['name' => ['required', 'string']],
            $resourceClass::creationRules(),
        );

        self::assertSame(
            [
                'id' => ['required', 'string'],
                'name' => ['required', 'string'],
            ],
            $resourceClass::updateRules(new ResourceFormRulesGeneratedKeyModel()),
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

    public function test_advanced_fields_infer_specialized_rules(): void
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
                    DateInput::make('published_on')
                        ->label('Published on')
                        ->required()
                        ->minDate('2026-01-01')
                        ->maxDate('2026-12-31'),
                    FileUpload::make('receipt')
                        ->label('Receipt')
                        ->maxSize(2048)
                        ->mimes(['jpg', 'png']),
                    FileUpload::make('attachments')
                        ->label('Attachments')
                        ->multiple()
                        ->maxFiles(2)
                        ->mimeTypes(['application/pdf']),
                    RichText::make('body')
                        ->label('Body')
                        ->required()
                        ->minLength(20)
                        ->maxLength(500),
                    RichText::make('content_json')
                        ->label('Content JSON')
                        ->json(),
                    PasswordInput::make('password')
                        ->label('Password')
                        ->minLength(12)
                        ->maxLength(72)
                        ->confirmed(),
                ]);
            }
        };

        $rules = $resourceClass::creationRules();

        self::assertSame(
            ['required', 'date_format:Y-m-d', 'after_or_equal:2026-01-01', 'before_or_equal:2026-12-31'],
            $rules['published_on'],
        );
        self::assertSame(['nullable', 'file', 'max:2048', 'mimes:jpg,png'], $rules['receipt']);
        self::assertSame(['nullable', 'boolean'], $rules['receipt__remove']);
        self::assertSame(['nullable', 'array', 'max:2'], $rules['attachments']);
        self::assertSame(['nullable', 'boolean'], $rules['attachments__remove']);
        self::assertSame(['file', 'mimetypes:application/pdf'], $rules['attachments.*']);
        self::assertSame(['required', 'string', 'min:20', 'max:500'], $rules['body']);
        self::assertSame(['nullable', 'array'], $rules['content_json']);
        self::assertSame(['nullable', 'string', 'min:12', 'max:72', 'confirmed'], $rules['password']);
    }

    public function test_belongs_to_fields_infer_exists_rules_from_relation_metadata(): void
    {
        self::assertSame(
            ['required', 'exists:belongs_to_categories,id'],
            BelongsToProductResource::creationRules()['category_id'],
        );
    }

    public function test_belongs_to_exists_rules_merge_form_builder_rules(): void
    {
        $resourceClass = new class() extends Resource
        {
            public static function model(): string
            {
                return ResourceFormRulesBelongsToProduct::class;
            }

            public static function form(FormContract $form): FormContract
            {
                return $form
                    ->schema([
                        BelongsTo::make('category_id', 'Category')
                            ->model(BelongsToCategory::class)
                            ->foreignKey('category_id')
                            ->ownerKey('uuid')
                            ->required(),
                    ])
                    ->rules([
                        'category_id' => ['integer'],
                    ]);
            }
        };

        self::assertSame(
            ['required', 'integer', 'exists:belongs_to_categories,uuid'],
            $resourceClass::creationRules()['category_id'],
        );
    }
}

final class ResourceFormRulesGeneratedKeyModel extends \Illuminate\Database\Eloquent\Model
{
}

final class ResourceFormRulesBelongsToProduct extends \Illuminate\Database\Eloquent\Model
{
    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(BelongsToCategory::class, 'category_id', 'uuid');
    }
}
