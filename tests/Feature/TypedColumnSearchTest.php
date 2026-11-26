<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Pepperfm\Flashboard\Tests\PostgresTestConnection;
use Pepperfm\Flashboard\Integration\Laravel\DataSources\TypedColumnSearch;
use Pepperfm\Flashboard\Tests\TestCase;

final class TypedColumnSearchTest extends TestCase
{
    public function test_native_postgresql_uuid_search_preserves_scope_and_text_matching(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection(PostgresTestConnection::configuration());
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        $schema = $capsule->schema();
        $schema->create('flashboard_search_probe', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->integer('tenant');
        });
        $schema->create('flashboard_search_join', static function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->uuid('probe_id');
        });
        try {
            $uuid = 'f7f1499b-e13d-4226-8689-4746aaea2461';
            $other = '0b963309-8487-4658-a7bd-ff5999bc7a1d';
            $capsule->table('flashboard_search_probe')->insert([
                ['id' => $uuid, 'title' => '100% literal', 'tenant' => 1],
                ['id' => $other, 'title' => '100% literal', 'tenant' => 2],
            ]);

            $capsule->table('flashboard_search_join')->insert([
                'id' => 'text-key', 'probe_id' => $uuid,
            ]);

            $search = new TypedColumnSearch;
            $query = SearchProbeRecord::query()->where('tenant', 1);
            $search->apply($query, ['id', 'title'], $uuid);
            self::assertSame([$uuid], $query->pluck('id')->all());

            $query = SearchProbeRecord::query()->where('tenant', 1);
            $search->apply($query, ['id'], 'f7f1499b');
            self::assertSame(0, $query->count());

            $query = SearchProbeRecord::query()->where('tenant', 1);
            $search->apply($query, ['id', 'title'], '%');
            self::assertSame([$uuid], $query->pluck('id')->all());

            $query = SearchProbeRecord::query()->where('tenant', 1);
            $search->apply($query, ['id', 'title'], $other);
            self::assertSame(0, $query->count());
            $query = SearchProbeRecord::query();
            $query->join('flashboard_search_join as joined', 'joined.probe_id', '=', 'flashboard_search_probe.id');
            $search->apply($query, ['flashboard_search_probe.id', 'joined.id'], 'text');
            self::assertSame([$uuid], $query->pluck('flashboard_search_probe.id')->all());

            $query = SearchProbeRecord::query();
            $query->join('flashboard_search_join', 'flashboard_search_join.probe_id', '=', 'flashboard_search_probe.id');
            $search->apply($query, ['flashboard_search_join.probe_id'], 'invalid');
            self::assertSame(0, $query->count());
            $query = SearchProbeRecord::query();
            $query->join('flashboard_search_join as joined', 'joined.probe_id', '=', 'flashboard_search_probe.id');
            $search->apply($query, ['joined.probe_id'], $uuid);
            self::assertSame([$uuid], $query->pluck('flashboard_search_probe.id')->all());
        } finally {
            $schema->dropIfExists('flashboard_search_join');
            $schema->dropIfExists('flashboard_search_probe');
        }
    }
}

final class SearchProbeRecord extends Model
{
    protected $table = 'flashboard_search_probe';

    protected $keyType = 'string';

    public $incrementing = false;
}
