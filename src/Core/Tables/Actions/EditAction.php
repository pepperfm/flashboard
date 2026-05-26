<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Tables\Actions;

use Pepperfm\Flashboard\Contracts\Tables\TableActionContract;

final class EditAction
{
    public static function make(): TableActionContract
    {
        return TableAction::edit();
    }
}
