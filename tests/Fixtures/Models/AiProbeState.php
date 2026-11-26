<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Fixtures\Models;

enum AiProbeState: int
{
    case Draft = 0;
    case Published = 1;

    public function label(): never
    {
        throw new \LogicException('The generator must not execute arbitrary enum methods.');
    }
}
