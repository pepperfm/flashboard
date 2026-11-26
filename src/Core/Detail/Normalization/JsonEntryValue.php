<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Detail\Normalization;

final class JsonEntryValue
{
    public const int MAX_INPUT_BYTES = 65536;
    public const int MAX_OUTPUT_BYTES = 32768;
    public const int MAX_DEPTH = 8;
    public const int MAX_ITEMS = 200;
    public const int MAX_STRING_BYTES = 2048;

    private int $remaining = self::MAX_ITEMS;

    private bool $truncated = false;

    /**
     * @return array{status: string, text: string|null}
     */
    public static function present(mixed $value, bool $decodeString = true): array
    {
        $normalizer = new self();

        try {
            if ($decodeString && is_string($value)) {
                if (strlen($value) > self::MAX_INPUT_BYTES) {
                    return ['status' => 'truncated', 'text' => null];
                }

                $value = json_decode($value, false, 32, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
            }

            $normalized = $normalizer->normalize($value);
            $text = json_encode($normalized, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

            if (strlen($text) > self::MAX_OUTPUT_BYTES) {
                return ['status' => 'truncated', 'text' => null];
            }

            return ['status' => $normalizer->truncated ? 'truncated' : 'ok', 'text' => $text];
        } catch (\JsonException $exception) {
            return ['status' => $exception->getCode() === JSON_ERROR_DEPTH ? 'truncated' : 'invalid', 'text' => null];
        } catch (\InvalidArgumentException) {
            return ['status' => 'invalid', 'text' => null];
        }
    }

    private function normalize(mixed $value, int $depth = 0): mixed
    {
        if ($depth > self::MAX_DEPTH || $this->remaining-- <= 0) {
            $this->truncated = true;

            return null;
        }
        if (is_string($value)) {
            if (strlen($value) > self::MAX_STRING_BYTES) {
                $this->truncated = true;

                return mb_strcut($value, 0, self::MAX_STRING_BYTES, 'UTF-8');
            }

            return $value;
        }
        if (is_scalar($value) || $value === null) {
            return $value;
        }
        if (!is_array($value) && (!is_object($value) || $value::class !== \stdClass::class)) {
            throw new \InvalidArgumentException('JSON entries require plain JSON values.');
        }

        $list = is_array($value) && array_is_list($value);
        $items = [];

        foreach ($value as $key => $item) {
            if ($this->remaining <= 0) {
                $this->truncated = true;
                break;
            }
            if (is_string($key) && strlen($key) > self::MAX_STRING_BYTES) {
                --$this->remaining;
                $this->truncated = true;
                continue;
            }

            $items[$key] = $this->normalize($item, $depth + 1);
        }

        return $list ? array_values($items) : (object) $items;
    }
}
