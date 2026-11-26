<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Fixtures;

final class DoctorProbePolicy
{
    public function approve(): never
    {
        throw new \RuntimeException('Diagnostic checks must not execute a policy method.');
    }
}
