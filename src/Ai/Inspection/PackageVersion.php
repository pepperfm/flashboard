<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Ai\Inspection;

use Composer\InstalledVersions;

final class PackageVersion
{
    public static function current(): string
    {
        if (InstalledVersions::isInstalled('pepperfm/flashboard')) {
            return InstalledVersions::getPrettyVersion('pepperfm/flashboard') ?? 'dev';
        }

        return 'dev';
    }
}
