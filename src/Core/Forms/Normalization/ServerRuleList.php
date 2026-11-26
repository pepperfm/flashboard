<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Forms\Normalization;

use Illuminate\Support\Arr;

final class ServerRuleList
{
    /** @return list<mixed> */
    public static function merge(mixed $first, mixed $second): array
    {
        $rules = [];
        $seen = [];

        foreach (array_merge(Arr::wrap($first), Arr::wrap($second)) as $rule) {
            if (is_string($rule)) {
                if (isset($seen[$rule])) {
                    continue;
                }

                $seen[$rule] = true;
            }

            // Do not stringify or compare internal state of rule objects or closures.
            $rules[] = $rule;
        }

        return $rules;
    }
}
