<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Pepperfm\Flashboard\Core\Runtime\Payloads\TablePayload;
use Pepperfm\Flashboard\Core\Tables\Builders\Table;
use Pepperfm\Flashboard\Core\Tables\Filters\SelectFilter;
use Pepperfm\Flashboard\Tests\TestCase;

final class TableFilterPayloadTest extends TestCase
{
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
}
