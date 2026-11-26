<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Pepperfm\Flashboard\Core\Detail\Normalization\JsonEntryValue;
use PHPUnit\Framework\TestCase;

final class JsonEntryValueTest extends TestCase
{
    public function test_plain_json_values_are_pretty_printed_without_serializing_objects(): void
    {
        foreach ([
            ['{}', '{}'], ['[]', '[]'], ['null', 'null'], ['"hello"', '"hello"'],
            [null, 'null'], [true, 'true'], [42, '42'],
        ] as [$value, $expected]) {
            self::assertSame(['status' => 'ok', 'text' => $expected], JsonEntryValue::present($value));
        }
        self::assertSame(['status' => 'ok', 'text' => '"plain"'], JsonEntryValue::present('plain', decodeString: false));
        self::assertSame(['status' => 'invalid', 'text' => null], JsonEntryValue::present('{invalid'));
        $object = new class implements \JsonSerializable
        {
            public function jsonSerialize(): mixed
            {
                throw new \LogicException('An arbitrary serializer must not be invoked.');
            }
        };
        self::assertSame(['status' => 'invalid', 'text' => null], JsonEntryValue::present(['object' => $object]));
    }

    public function test_json_limits_bound_output_before_transport(): void
    {
        self::assertSame(['status' => 'truncated', 'text' => null], JsonEntryValue::present(str_repeat('x', 65537)));

        $wide = JsonEntryValue::present(range(1, 1000));
        self::assertSame('truncated', $wide['status']);
        self::assertIsString($wide['text']);
        self::assertCount(199, json_decode($wide['text'], true, flags: JSON_THROW_ON_ERROR));

        $deep = [];
        for ($i = 0; $i < 20; ++$i) {
            $deep = ['nested' => $deep];
        }
        self::assertSame('truncated', JsonEntryValue::present($deep)['status']);

        $long = JsonEntryValue::present(['text' => str_repeat('é', 2000)]);
        self::assertSame('truncated', $long['status']);
        self::assertIsString($long['text']);
        self::assertSame(2048, strlen(json_decode($long['text'], true, flags: JSON_THROW_ON_ERROR)['text']));
        self::assertSame(['status' => 'truncated', 'text' => null], JsonEntryValue::present(array_fill(0, 100, str_repeat('x', 2048))));
    }
}
