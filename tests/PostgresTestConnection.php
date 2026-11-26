<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests;

use PHPUnit\Framework\Assert;

final class PostgresTestConnection
{
    /** @return array<string, int|string> */
    public static function configuration(): array
    {
        $database = getenv('FLASHBOARD_PG_TEST_DB');

        if ($database === false || $database === '') {
            Assert::markTestSkipped('Provide an isolated flashboard_test_ PostgreSQL database.');
        }

        if (!str_starts_with($database, 'flashboard_test_')) {
            throw new \InvalidArgumentException('PostgreSQL tests require an isolated flashboard_test_ database.');
        }

        return [
            'driver' => 'pgsql',
            'host' => self::environment('FLASHBOARD_PG_TEST_HOST', '/tmp'),
            'port' => (int) self::environment('FLASHBOARD_PG_TEST_PORT', '5432'),
            'database' => $database,
            'username' => self::environment('FLASHBOARD_PG_TEST_USER', get_current_user()),
            'password' => self::environment('FLASHBOARD_PG_TEST_PASSWORD', ''),
            'prefix' => '',
        ];
    }

    private static function environment(string $key, string $default): string
    {
        $value = getenv($key);

        return $value === false ? $default : $value;
    }
}
