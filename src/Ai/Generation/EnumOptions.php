<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Ai\Generation;

use BackedEnum;

final class EnumOptions
{
    /**
     * @return array{
     *     class: class-string<BackedEnum>,
     *     options: array<int|string, string>,
     *     backing: string
     * }|null
     */
    public function inspect(\Illuminate\Database\Eloquent\Model $model, string $field): ?array
    {
        $cast = $model->getCasts()[$field] ?? null;

        if (!is_string($cast) || !is_subclass_of($cast, BackedEnum::class) || !enum_exists($cast)) {
            return null;
        }

        $options = [];
        foreach ($cast::cases() as $case) {
            $options[$case->value] = str($case->name)->headline()->value();
        }

        $backing = new \ReflectionEnum($cast)->getBackingType()?->getName() ?? 'string';

        return ['class' => $cast, 'options' => $options, 'backing' => $backing];
    }
}
