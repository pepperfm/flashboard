<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Pepperfm\Flashboard\Core\Runtime\Payloads\SchemaVersion;
use Pepperfm\Flashboard\Core\Runtime\Payloads\ScreenPayload;
use Pepperfm\Flashboard\Tests\TestCase;

final class ScreenPayloadSchemaTest extends TestCase
{
    public function test_screen_payload_contains_schema_version(): void
    {
        $payload = new ScreenPayload([
            'page' => [
                'key' => 'dashboard',
            ],
        ]);

        self::assertSame(SchemaVersion::V1->value, $payload->toArray()['schema_version']);
    }
}
