<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Actions;

enum ActionScope: string
{
    case Legacy = 'legacy';
    case Collection = 'collection';
    case Record = 'record';

    public function acceptsRecord(bool $hasRecord): bool
    {
        return match ($this) {
            self::Legacy => true,
            self::Collection => ! $hasRecord,
            self::Record => $hasRecord,
        };
    }
}
