<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Fixtures\Resources;

use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Tests\Fixtures\Models\AiHarnessTag;

final class AiHarnessTagResource extends Resource
{
    public static function model(): string
    {
        return AiHarnessTag::class;
    }
}
