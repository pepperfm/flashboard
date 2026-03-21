<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Pepperfm\Flashboard\Contracts\Detail\DetailContract;
use Pepperfm\Flashboard\Contracts\Forms\FormContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Contracts\Tables\TableContract;
use Pepperfm\Flashboard\Core\Detail\Entries\TextEntry;
use Pepperfm\Flashboard\Core\Detail\Layout\Section as DetailSection;
use Pepperfm\Flashboard\Core\Forms\Fields\TextInput;
use Pepperfm\Flashboard\Core\Forms\Layout\Section as FormSection;
use Pepperfm\Flashboard\Core\Runtime\Assemblers\DetailPayloadAssembler;
use Pepperfm\Flashboard\Core\Runtime\Payloads\DetailPayload;
use Pepperfm\Flashboard\Core\Runtime\Payloads\FormPayload;
use Pepperfm\Flashboard\Core\Runtime\Payloads\TablePayload;
use Pepperfm\Flashboard\Core\Tables\Columns\BadgeColumn;
use Pepperfm\Flashboard\Core\Tables\Columns\TextColumn;
use Pepperfm\Flashboard\Core\Tables\Builders\Table;
use Pepperfm\Flashboard\Core\Forms\Builders\Form;
use Pepperfm\Flashboard\Core\Detail\Builders\Detail;
use Pepperfm\Flashboard\Tests\TestCase;

final class TypedSchemaNormalizationTest extends TestCase
{
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

    public function test_form_builder_flattens_typed_sections_into_runtime_fields(): void
    {
        $payload = new FormPayload(
            Form::make()
                ->sections([
                    FormSection::make('main')->label('Main')->schema([
                        TextInput::make('email')->label('Email')->email()->required(),
                    ]),
                ])
                ->rules([
                    'email' => ['required', 'email'],
                ])
                ->toArray(),
        );

        self::assertCount(1, $payload->sections());
        self::assertSame('main', $payload->sections()[0]['key']);
        self::assertCount(1, $payload->fields());
        self::assertSame('email', $payload->fields()[0]['key']);
        self::assertSame('email', $payload->fields()[0]['input_type']);
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
}
