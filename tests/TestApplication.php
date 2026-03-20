<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests;

use Illuminate\Container\Container;

final class TestApplication extends Container
{
    public function runningInConsole(): bool
    {
        return false;
    }
}
