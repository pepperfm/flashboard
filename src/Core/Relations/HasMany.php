<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Relations;

final class HasMany extends RelationManagerDefinition
{
    public const string TYPE = 'has_many';

    protected function type(): string
    {
        return self::TYPE;
    }
}
