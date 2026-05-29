<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Relations\Concerns;

trait InfersRelationshipName
{
    protected static function inferRelationshipName(string $key): string
    {
        $key = trim($key);

        if ($key === '') {
            return $key;
        }

        return $key;
    }
}
