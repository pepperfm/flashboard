<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests;

use Illuminate\Container\Container;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class TestApplication extends Container
{
    public function basePath($path = ''): string
    {
        $basePath = getcwd() ?: __DIR__ . '/..';

        if ($path === '' || $path === null) {
            return $basePath;
        }

        return rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim((string) $path, DIRECTORY_SEPARATOR);
    }

    public function runningInConsole(): bool
    {
        return false;
    }

    /**
     * @param array<string, mixed> $headers
     */
    public function abort(int $code, string $message = '', array $headers = []): never
    {
        throw new HttpException($code, $message, null, $headers);
    }
}
