<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Pepperfm\Flashboard\Core\Relations\HasMany;
use Pepperfm\Flashboard\Core\Relations\HasOne;
use Pepperfm\Flashboard\Core\Relations\RelationDefinition;
use Pepperfm\Flashboard\Tests\Fixtures\Models\BelongsToCategory;
use Pepperfm\Flashboard\Tests\Fixtures\Resources\BelongsToCategoryResource;
use Pepperfm\Flashboard\Tests\TestCase;

final class RelationDefinitionPayloadTest extends TestCase
{
    public function test_has_one_accepts_key_label_and_relationship(): void
    {
        $payload = HasOne::make('primary_profile', 'Primary profile', 'profile')
            ->resource(BelongsToCategoryResource::class)
            ->model(BelongsToCategory::class)
            ->localKey('uuid')
            ->foreignKey('user_uuid')
            ->recordKeyName('uuid')
            ->titleAttribute('display_name')
            ->searchable(['display_name', 'email'])
            ->attachable()
            ->detachable()
            ->replaceable()
            ->showOnEdit()
            ->toArray();

        self::assertSame('has_one', $payload['type']);
        self::assertSame('primary_profile', $payload['key']);
        self::assertSame('Primary profile', $payload['label']);
        self::assertSame('profile', $payload['relationship']);
        self::assertSame(BelongsToCategoryResource::class, $payload['related_resource']);
        self::assertSame(BelongsToCategory::class, $payload['related_model']);
        self::assertSame('uuid', $payload['local_key']);
        self::assertSame('user_uuid', $payload['foreign_key']);
        self::assertSame('uuid', $payload['record_key_name']);
        self::assertSame('display_name', $payload['title_attribute']);
        self::assertSame(['display_name', 'email'], $payload['search_columns']);
        self::assertTrue($payload['attachable']);
        self::assertTrue($payload['detachable']);
        self::assertTrue($payload['replaceable']);
        self::assertFalse($payload['syncable']);
        self::assertTrue($payload['show_on_detail']);
        self::assertTrue($payload['show_on_edit']);
    }

    public function test_has_many_accepts_overrides_and_sync_flags(): void
    {
        $payload = HasMany::make('recent_orders', 'Recent orders', 'orders')
            ->perPage(25)
            ->searchable('number')
            ->readOnly(false)
            ->attachable()
            ->detachable()
            ->syncable()
            ->toArray();

        self::assertSame('has_many', $payload['type']);
        self::assertSame('recent_orders', $payload['key']);
        self::assertSame('Recent orders', $payload['label']);
        self::assertSame('orders', $payload['relationship']);
        self::assertSame(['number'], $payload['search_columns']);
        self::assertSame(25, $payload['per_page']);
        self::assertTrue($payload['attachable']);
        self::assertTrue($payload['detachable']);
        self::assertFalse($payload['replaceable']);
        self::assertTrue($payload['syncable']);
    }

    public function test_relation_query_modifiers_are_server_only(): void
    {
        $definition = HasMany::make('items', 'Items')
            ->modifyQueryUsing(static fn (\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder => $query);
        $payload = $definition->toArray();

        self::assertInstanceOf(\Closure::class, $definition->recordsQueryModifier());
        self::assertInstanceOf(\Closure::class, $definition->attachOptionsQueryModifier());
        self::assertArrayNotHasKey('modify_query_using', $payload);
        self::assertArrayNotHasKey('records_query_modifier', $payload);
        self::assertArrayNotHasKey('attach_options_query_modifier', $payload);
    }

    public function test_relationship_defaults_to_relation_key(): void
    {
        self::assertSame('profile', HasOne::make('profile')->toArray()['relationship']);
        self::assertSame('orders', HasMany::make('orders')->toArray()['relationship']);
    }

    public function test_legacy_relation_definition_stays_backward_compatible(): void
    {
        $payload = RelationDefinition::make('items', 'Items', 'orderItems')
            ->titleAttribute('name')
            ->recordKeyName('uuid')
            ->visible(false)
            ->toArray();

        self::assertSame('relation', $payload['type']);
        self::assertSame('items', $payload['key']);
        self::assertSame('Items', $payload['label']);
        self::assertSame('orderItems', $payload['relationship']);
        self::assertSame('name', $payload['title_attribute']);
        self::assertSame('uuid', $payload['record_key_name']);
        self::assertFalse($payload['visible']);
    }
}
