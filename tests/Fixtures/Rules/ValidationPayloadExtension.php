<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Fixtures\Rules;

use Pepperfm\Flashboard\Contracts\Extensions\PayloadExtensionContract;

final class ValidationPayloadExtension implements PayloadExtensionContract
{
    public function extend(string $resourceClass, string $screenPage, array $payload): array
    {
        if (!in_array($screenPage, ['create', 'edit'], true)) {
            return $payload;
        }

        // Exercise both the form data source and the outer screen payload extension.
        if (isset($payload['form']) && is_array($payload['form'])) {
            $payload['form']['rules'] = [new ServerOnlyNameRule()];
            $payload['form']['fields'][0]['rules'] = [new ServerOnlyNameRule()];

            return $payload;
        }

        $payload['rules'] = [new ServerOnlyNameRule()];
        $payload['schema'][0]['rules'] = [new ServerOnlyNameRule()];

        return $payload;
    }
}
