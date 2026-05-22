<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Pepperfm\Flashboard\Core\Runtime\Payloads\TablePayload;
use Pepperfm\Flashboard\Core\Tables\Builders\Table;
use Pepperfm\Flashboard\Core\Tables\Filters\DateFilter;
use Pepperfm\Flashboard\Core\Tables\Filters\InputFilter;
use Pepperfm\Flashboard\Core\Tables\Filters\SelectFilter;
use Pepperfm\Flashboard\Tests\TestCase;

final class TableFilterPayloadTest extends TestCase
{
    public function test_date_filters_are_exposed_in_table_payload(): void
    {
        $payload = new TablePayload(
            Table::make()
                ->filters([
                    DateFilter::make('created_at')->label('Created'),
                ])
                ->toArray(),
        );

        self::assertSame(
            [
                [
                    'key' => 'created_at',
                    'label' => 'Created',
                    'type' => 'date',
                ],
            ],
            $payload->filters(),
        );
    }

    public function test_date_filters_can_target_a_different_query_column(): void
    {
        $payload = new TablePayload(
            Table::make()
                ->filters([
                    DateFilter::make('published')
                        ->label('Published')
                        ->queryColumn('posts.published_at'),
                ])
                ->toArray(),
        );

        self::assertSame([
            [
                'key' => 'published',
                'label' => 'Published',
                'type' => 'date',
                'query_column' => 'posts.published_at',
            ],
        ], $payload->filters());
    }

    public function test_input_filters_are_exposed_in_table_payload(): void
    {
        $payload = new TablePayload(
            Table::make()
                ->filters([
                    InputFilter::make('email')->label('Email'),
                ])
                ->toArray(),
        );

        self::assertSame(
            [
                [
                    'key' => 'email',
                    'label' => 'Email',
                    'type' => 'input',
                ],
            ],
            $payload->filters(),
        );
    }

    public function test_input_filters_can_be_marked_as_contains_matches(): void
    {
        $payload = new TablePayload(
            Table::make()
                ->filters([
                    InputFilter::make('email')
                        ->label('Email')
                        ->contains(),
                ])
                ->toArray(),
        );

        self::assertSame([
            [
                'key' => 'email',
                'label' => 'Email',
                'type' => 'input',
                'match' => 'contains',
            ],
        ], $payload->filters());
    }

    public function test_input_filters_can_target_a_different_query_column(): void
    {
        $payload = new TablePayload(
            Table::make()
                ->filters([
                    InputFilter::make('email')
                        ->label('Email')
                        ->queryColumn('users.email')
                        ->exact(),
                ])
                ->toArray(),
        );

        self::assertSame([
            [
                'key' => 'email',
                'label' => 'Email',
                'type' => 'input',
                'query_column' => 'users.email',
                'match' => 'exact',
            ],
        ], $payload->filters());
    }

    public function test_select_filters_are_exposed_in_table_payload(): void
    {
        $payload = new TablePayload(
            Table::make()
                ->filters([
                    SelectFilter::make('status')
                        ->label('Status')
                        ->options([
                            'draft' => 'Draft',
                            'published' => 'Published',
                        ]),
                ])
                ->toArray(),
        );

        self::assertSame(
            [
                [
                    'key' => 'status',
                    'label' => 'Status',
                    'type' => 'select',
                    'options' => [
                        'draft' => 'Draft',
                        'published' => 'Published',
                    ],
                ],
            ],
            $payload->filters(),
        );
    }

    public function test_select_filters_can_be_marked_searchable(): void
    {
        $payload = new TablePayload(
            Table::make()
                ->filters([
                    SelectFilter::make('sku')
                        ->label('SKU')
                        ->searchable()
                        ->options([
                            'SKU-1' => 'SKU-1',
                            'SKU-2' => 'SKU-2',
                        ]),
                ])
                ->toArray(),
        );

        self::assertTrue($payload->filters()[0]['searchable']);
    }

    public function test_select_filters_can_target_a_different_query_column(): void
    {
        $payload = new TablePayload(
            Table::make()
                ->filters([
                    SelectFilter::make('sku')
                        ->label('SKU')
                        ->queryColumn('id')
                        ->options([
                            1 => 'SKU-1',
                            2 => 'SKU-2',
                        ]),
                ])
                ->toArray(),
        );

        self::assertSame('id', $payload->filters()[0]['query_column']);
        self::assertSame([
            1 => 'SKU-1',
            2 => 'SKU-2',
        ], $payload->filters()[0]['options']);
    }

    public function test_select_filters_can_be_marked_multiple(): void
    {
        $payload = new TablePayload(
            Table::make()
                ->filters([
                    SelectFilter::make('status')
                        ->label('Status')
                        ->multiple()
                        ->options([
                            'draft' => 'Draft',
                            'published' => 'Published',
                        ]),
                ])
                ->toArray(),
        );

        self::assertSame([
            [
                'key' => 'status',
                'label' => 'Status',
                'type' => 'select',
                'multiple' => true,
                'options' => [
                    'draft' => 'Draft',
                    'published' => 'Published',
                ],
            ],
        ], $payload->filters());
    }

    public function test_select_filters_do_not_serialize_multiple_when_disabled(): void
    {
        $payload = new TablePayload(
            Table::make()
                ->filters([
                    SelectFilter::make('status')
                        ->label('Status')
                        ->multiple(false),
                ])
                ->toArray(),
        );

        self::assertSame([
            [
                'key' => 'status',
                'label' => 'Status',
                'type' => 'select',
            ],
        ], $payload->filters());
    }

    public function test_select_filters_can_be_marked_lazy_without_serializing_resolvers(): void
    {
        $payload = new TablePayload(
            Table::make()
                ->filters([
                    SelectFilter::make('status')
                        ->label('Status')
                        ->lazy(static fn (): array => [
                            [
                                'label' => 'Draft',
                                'value' => 'draft',
                            ],
                        ], perPage: 15),
                ])
                ->toArray(),
        );

        self::assertSame([
            [
                'key' => 'status',
                'label' => 'Status',
                'type' => 'select',
                'lazy' => true,
                'options_per_page' => 15,
            ],
        ], $payload->filters());
    }

    public function test_lazy_select_filters_can_define_option_label_and_value_columns(): void
    {
        $payload = new TablePayload(
            Table::make()
                ->filters([
                    SelectFilter::make('sku')
                        ->label('SKU')
                        ->lazy()
                        ->optionValue('id')
                        ->optionLabel('sku'),
                ])
                ->toArray(),
        );

        self::assertSame([
            [
                'key' => 'sku',
                'label' => 'SKU',
                'type' => 'select',
                'lazy' => true,
                'options_per_page' => SelectFilter::DEFAULT_OPTIONS_PER_PAGE,
                'query_column' => 'id',
                'option_value_column' => 'id',
                'option_label_column' => 'sku',
            ],
        ], $payload->filters());
    }

    public function test_lazy_select_filters_can_be_marked_multiple(): void
    {
        $payload = new TablePayload(
            Table::make()
                ->filters([
                    SelectFilter::make('status')
                        ->label('Status')
                        ->lazy(perPage: 15)
                        ->multiple(),
                ])
                ->toArray(),
        );

        self::assertSame([
            [
                'key' => 'status',
                'label' => 'Status',
                'type' => 'select',
                'lazy' => true,
                'options_per_page' => 15,
                'multiple' => true,
            ],
        ], $payload->filters());
    }
}
