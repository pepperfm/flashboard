<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Fixtures\Rules;

use Illuminate\Contracts\Validation\ValidationRule;

final class ServerOnlyNameRule implements ValidationRule
{
    public string $configuration = 'server-only-rule-configuration';

    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        if ($value === 'class-denied') {
            $fail('Name rejected by class.');
        }
    }
}
