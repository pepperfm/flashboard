<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Resources\Relations;

interface RelationManagerContract
{
    /**
     * @return list<RelationDefinitionContract>
     */
    public static function relations(): array;
}
