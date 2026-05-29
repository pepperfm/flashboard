<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Relations;

final class HasOne extends RelationManagerDefinition
{
    public const string TYPE = 'has_one';

    protected function type(): string
    {
        return self::TYPE;
    }
}
