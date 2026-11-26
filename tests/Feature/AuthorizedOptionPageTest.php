<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Pepperfm\Flashboard\Integration\Laravel\DataSources\AuthorizedOptionPage;
use Pepperfm\Flashboard\Tests\TestCase;

final class AuthorizedOptionPageTest extends TestCase
{
    public function test_denied_records_do_not_truncate_pages_or_duplicate_results(): void
    {
        $database = new Capsule();
        $database->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $database->setAsGlobal();
        $database->bootEloquent();
        $database->schema()->create('authorized_page_records', static function (Blueprint $table): void {
            $table->increments('id');
        });
        foreach (range(1, 150) as $id) {
            $database->table('authorized_page_records')->insert(['id' => $id]);
        }

        $paginator = new AuthorizedOptionPage();
        $map = static fn (Model $model): ?array => $model->getKey() > 120 ? ['id' => $model->getKey()] : null;
        $query = AuthorizedPageRecord::query();
        $query->orderBy('id');
        $first = $paginator->resolve($query, 1, 10, $map, true);
        $query = AuthorizedPageRecord::query();
        $query->orderBy('id');
        $second = $paginator->resolve($query, 2, 10, $map, true);
        $query = AuthorizedPageRecord::query();
        $query->orderBy('id');
        $third = $paginator->resolve($query, 3, 10, $map, true);

        self::assertSame(range(121, 131), array_column($first, 'id'));
        self::assertSame(range(131, 141), array_column($second, 'id'));
        self::assertSame(range(141, 150), array_column($third, 'id'));
    }
}

final class AuthorizedPageRecord extends Model
{
    protected $table = 'authorized_page_records';
}
