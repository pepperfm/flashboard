<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard;

final class Flashboard
{
    public const string VERSION = '0.1.4';

    public const string CONFIG_NAME = 'flashboard';

    private static ?FlashboardConfig $config = null;

    public static function configure(?callable $callback = null): FlashboardConfig
    {
        self::$config ??= new FlashboardConfig();

        if ($callback !== null) {
            $callback(self::$config);
        }

        return self::$config;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public static function resolvedConfig(array $config): array
    {
        return self::configure()->merge($config);
    }

    public static function resetConfiguration(): void
    {
        self::$config?->reset();
    }
}
