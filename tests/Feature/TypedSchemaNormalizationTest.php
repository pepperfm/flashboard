<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Pepperfm\Flashboard\Contracts\Forms\FieldRenderer;
use Pepperfm\Flashboard\Contracts\Detail\DetailContract;
use Pepperfm\Flashboard\Contracts\Forms\FormContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Contracts\Tables\TableContract;
use Pepperfm\Flashboard\Core\Detail\Entries\TextEntry;
use Pepperfm\Flashboard\Core\Forms\Fields\BelongsTo;
use Pepperfm\Flashboard\Core\Forms\Fields\BelongsToMany;
use Pepperfm\Flashboard\Core\Detail\Layout\Section as DetailSection;
use Pepperfm\Flashboard\Core\Forms\Fields\Checkbox;
use Pepperfm\Flashboard\Core\Forms\Fields\DateInput;
use Pepperfm\Flashboard\Core\Forms\Fields\FileUpload;
use Pepperfm\Flashboard\Core\Forms\Fields\NumberInput;
use Pepperfm\Flashboard\Core\Forms\Fields\PasswordInput;
use Pepperfm\Flashboard\Core\Forms\Fields\RichText;
use Pepperfm\Flashboard\Core\Forms\Fields\Select;
use Pepperfm\Flashboard\Core\Forms\Fields\Textarea;
use Pepperfm\Flashboard\Core\Forms\Fields\TextInput;
use Pepperfm\Flashboard\Core\Forms\Fields\Toggle;
use Pepperfm\Flashboard\Core\Forms\Layout\Section as FormSection;
use Pepperfm\Flashboard\Core\Forms\Normalization\FormSchemaNormalizer;
use Pepperfm\Flashboard\Core\Relations\HasMany;
use Pepperfm\Flashboard\Core\Runtime\Assemblers\DetailPayloadAssembler;
use Pepperfm\Flashboard\Core\Runtime\Payloads\DetailPayload;
use Pepperfm\Flashboard\Core\Runtime\Payloads\FormPayload;
use Pepperfm\Flashboard\Core\Runtime\Payloads\TablePayload;
use Pepperfm\Flashboard\Core\Tables\Columns\BadgeColumn;
use Pepperfm\Flashboard\Core\Tables\Columns\DateColumn;
use Pepperfm\Flashboard\Core\Tables\Columns\TextColumn;
use Pepperfm\Flashboard\Core\Tables\Builders\Table;
use Pepperfm\Flashboard\Core\Tables\Filters\DateFilter;
use Pepperfm\Flashboard\Core\Tables\Filters\InputFilter;
use Pepperfm\Flashboard\Core\Tables\Filters\SelectFilter;
use Pepperfm\Flashboard\Core\Forms\Builders\Form;
use Pepperfm\Flashboard\Core\Detail\Builders\Detail;
use Pepperfm\Flashboard\Tests\TestCase;

final class TypedSchemaNormalizationTest extends TestCase
{
    public function test_typed_schema_nodes_accept_label_in_make_factory(): void
    {
        $nodes = [
            [TextInput::make('email', 'Email address'), 'Email address'],
            [Textarea::make('notes', 'Operator notes'), 'Operator notes'],
            [NumberInput::make('sort_order', 'Sort order'), 'Sort order'],
            [Select::make('status', 'Status'), 'Status'],
            [Checkbox::make('is_featured', 'Featured'), 'Featured'],
            [Toggle::make('is_active', 'Is active'), 'Is active'],
            [DateInput::make('published_on', 'Published on'), 'Published on'],
            [FileUpload::make('receipt', 'Receipt'), 'Receipt'],
            [RichText::make('body', 'Body'), 'Body'],
            [PasswordInput::make('password', 'Password'), 'Password'],
            [BelongsTo::make('category_id', 'Category', 'category'), 'Category'],
            [BelongsToMany::make('tags', 'Tags'), 'Tags'],
            [TextEntry::make('summary', 'Summary'), 'Summary'],
            [TextColumn::make('name', 'Name'), 'Name'],
            [BadgeColumn::make('status', 'Status badge'), 'Status badge'],
            [DateColumn::make('created_at', 'Created'), 'Created'],
            [InputFilter::make('email', 'Email filter'), 'Email filter'],
            [DateFilter::make('created_at', 'Created filter'), 'Created filter'],
            [SelectFilter::make('status', 'Status filter'), 'Status filter'],
            [FormSection::make('main', 'Main section'), 'Main section'],
            [DetailSection::make('summary', 'Summary section'), 'Summary section'],
        ];

        foreach ($nodes as [$node, $label]) {
            self::assertSame($label, $node->toArray()['label']);
        }
    }

    public function test_form_schema_rejects_relation_managers_with_actionable_message(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Define it in Resource::relations() and call showOnEdit()');

        (new FormSchemaNormalizer())->normalize([
            'schema' => [
                TextInput::make('name', 'Name'),
                HasMany::make('items', 'Items'),
            ],
        ]);
    }

    public function test_table_payload_accessors_use_normalized_column_metadata(): void
    {
        $payload = new TablePayload(
            Table::make()
                ->columns([
                    TextColumn::make('id')->label('ID')->sortable(),
                    BadgeColumn::make('status')->label('Status')->searchable(),
                ])
                ->toArray(),
        );

        self::assertSame(['id', 'status'], array_column($payload->columns(), 'key'));
        self::assertSame(['status'], $payload->searchableColumns());
        self::assertSame(['id'], $payload->sortableColumns());
    }

    public function test_date_columns_are_exposed_in_table_payload(): void
    {
        $payload = new TablePayload(
            Table::make()
                ->columns([
                    DateColumn::make('created_at')->label('Created')->sortable(),
                ])
                ->toArray(),
        );

        self::assertSame([
            [
                'key' => 'created_at',
                'label' => 'Created',
                'type' => 'date',
                'sortable' => true,
            ],
        ], $payload->columns());
        self::assertSame(['created_at'], $payload->sortableColumns());
    }

    public function test_date_columns_can_expose_a_php_date_format(): void
    {
        $payload = new TablePayload(
            Table::make()
                ->columns([
                    DateColumn::make('created_at')->label('Created')->format('d.m.Y'),
                ])
                ->toArray(),
        );

        self::assertSame([
            [
                'key' => 'created_at',
                'label' => 'Created',
                'type' => 'date',
                'format' => 'd.m.Y',
            ],
        ], $payload->columns());
    }

    public function test_form_builder_flattens_typed_sections_into_runtime_fields(): void
    {
        $payload = new FormPayload(
            Form::make()
                ->sections([
                    FormSection::make('main')->label('Main')->schema([
                        TextInput::make('email')->label('Email')->email()->required(),
                        Textarea::make('notes')->label('Notes'),
                        NumberInput::make('sort_order')->label('Sort order'),
                        Select::make('status')->label('Status')->options(['draft' => 'Draft']),
                        Checkbox::make('is_featured')->label('Featured'),
                        Toggle::make('is_active')->label('Is active'),
                    ]),
                ])
                ->rules([
                    'email' => ['required', 'email'],
                ])
                ->toArray(),
        );

        self::assertCount(1, $payload->sections());
        self::assertSame('main', $payload->sections()[0]['key']);
        self::assertCount(6, $payload->fields());
        self::assertSame('email', $payload->fields()[0]['key']);
        self::assertSame('email', $payload->fields()[0]['input_type']);
        self::assertSame(FieldRenderer::Input->value, $payload->fields()[0]['renderer']);
        self::assertSame(FieldRenderer::Textarea->value, $payload->fields()[1]['renderer']);
        self::assertSame('number', $payload->fields()[2]['input_type']);
        self::assertSame(FieldRenderer::Input->value, $payload->fields()[2]['renderer']);
        self::assertSame(FieldRenderer::Select->value, $payload->fields()[3]['renderer']);
        self::assertSame(FieldRenderer::Checkbox->value, $payload->fields()[4]['renderer']);
        self::assertSame(FieldRenderer::Switch->value, $payload->fields()[5]['renderer']);
        self::assertSame(FieldRenderer::Input->value, $payload->sections()[0]['schema'][0]['renderer']);
        self::assertSame(FieldRenderer::Textarea->value, $payload->sections()[0]['schema'][1]['renderer']);
        self::assertSame(FieldRenderer::Input->value, $payload->sections()[0]['schema'][2]['renderer']);
        self::assertSame(FieldRenderer::Select->value, $payload->sections()[0]['schema'][3]['renderer']);
        self::assertSame(FieldRenderer::Checkbox->value, $payload->sections()[0]['schema'][4]['renderer']);
        self::assertSame(FieldRenderer::Switch->value, $payload->sections()[0]['schema'][5]['renderer']);
        self::assertArrayHasKey('email', $payload->rules());
    }

    public function test_detail_builder_flattens_section_entries_into_runtime_payload(): void
    {
        $payload = new DetailPayload(
            Detail::make()
                ->sections([
                    DetailSection::make('summary')->label('Summary')->schema([
                        TextEntry::make('status')->label('Status'),
                    ]),
                ])
                ->toArray(),
        );

        self::assertCount(1, $payload->sections());
        self::assertSame('summary', $payload->sections()[0]['key']);
        self::assertCount(1, $payload->entries());
        self::assertSame('status', $payload->entries()[0]['key']);
    }

    public function test_detail_payload_assembler_reads_infolist_surface(): void
    {
        $resourceClass = get_class(new class extends Resource
        {
            public static function model(): string
            {
                return \Illuminate\Database\Eloquent\Model::class;
            }

            public static function infolist(DetailContract $detail): DetailContract
            {
                return $detail->entries([
                    TextEntry::make('status')->label('Status'),
                ]);
            }

            public static function table(TableContract $table): TableContract
            {
                return $table;
            }

            public static function form(FormContract $form): FormContract
            {
                return $form;
            }
        });

        $payload = (new DetailPayloadAssembler())->assemble($resourceClass)->toArray();

        self::assertCount(1, $payload['entries']);
        self::assertSame('status', $payload['entries'][0]['key']);
    }

    public function test_legacy_array_definitions_remain_normalized_for_runtime_consumers(): void
    {
        $payload = new TablePayload(
            Table::make()
                ->columns([
                    ['key' => 'id', 'label' => 'ID', 'sortable' => true],
                    ['key' => 'status', 'label' => 'Status', 'searchable' => true],
                ])
                ->toArray(),
        );

        self::assertSame(['id', 'status'], array_column($payload->columns(), 'key'));
        self::assertSame(['status'], $payload->searchableColumns());
        self::assertSame(['id'], $payload->sortableColumns());
    }

    public function test_legacy_array_form_fields_receive_same_renderer_contract_as_typed_fields(): void
    {
        $typedPayload = new FormPayload(
            Form::make()
                ->sections([
                    FormSection::make('main')->label('Main')->schema([
                        TextInput::make('email')->label('Email')->email()->required(),
                        Select::make('status')->label('Status')->options(['draft' => 'Draft']),
                        Toggle::make('is_active')->label('Is active'),
                    ]),
                ])
                ->toArray(),
        );

        $arrayPayload = new FormPayload(
            Form::make()
                ->sections([
                    FormSection::make('main')->label('Main')->schema([
                        ['key' => 'email', 'label' => 'Email', 'type' => 'text', 'input_type' => 'email', 'required' => true],
                        ['key' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['draft' => 'Draft']],
                        ['key' => 'is_active', 'label' => 'Is active', 'type' => 'toggle'],
                    ]),
                ])
                ->toArray(),
        );

        self::assertSame(
            array_column($typedPayload->fields(), 'renderer', 'key'),
            array_column($arrayPayload->fields(), 'renderer', 'key'),
        );
        self::assertSame(
            array_column($typedPayload->sections()[0]['schema'], 'renderer', 'key'),
            array_column($arrayPayload->sections()[0]['schema'], 'renderer', 'key'),
        );
    }

    public function test_advanced_typed_form_fields_normalize_to_runtime_payload_shape(): void
    {
        $payload = new FormPayload(
            Form::make()
                ->schema([
                    DateInput::make('published_on')->label('Published on'),
                    FileUpload::make('receipt')->label('Receipt'),
                    RichText::make('body')->label('Body')->json(),
                    PasswordInput::make('password')->label('Password'),
                ])
                ->toArray(),
        );

        self::assertSame(
            [
                'published_on' => FieldRenderer::Date->value,
                'receipt' => FieldRenderer::FileUpload->value,
                'body' => FieldRenderer::RichText->value,
                'password' => FieldRenderer::Input->value,
            ],
            array_column($payload->fields(), 'renderer', 'key'),
        );
        self::assertSame('json', $payload->fields()[2]['content_format']);
        self::assertSame('password', $payload->fields()[3]['input_type']);
    }
}
