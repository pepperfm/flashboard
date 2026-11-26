<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Fixtures\Resources;

use Illuminate\Validation\Rule;
use Pepperfm\Flashboard\Contracts\Forms\FormContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Forms\Fields\TextInput;
use Pepperfm\Flashboard\Tests\Fixtures\Models\AiProbeRecord;
use Pepperfm\Flashboard\Tests\Fixtures\Rules\ServerOnlyNameRule;

class ValidationProbeResource extends Resource
{
    public static function model(): string
    {
        return AiProbeRecord::class;
    }

    public static function key(): string
    {
        return 'validation_probe';
    }

    public static function form(FormContract $form): FormContract
    {
        return $form->schema([
            TextInput::make('name')->required(),
            TextInput::make('occurred_on')->required(),
        ])->rules(self::serverRules());
    }

    public static function payloadExtensions(): array
    {
        return [new \Pepperfm\Flashboard\Tests\Fixtures\Rules\ValidationPayloadExtension()];
    }

    protected static function serverRules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === 'reserved') {
                        $fail('Name reserved by closure.');
                    }
                },
                new ServerOnlyNameRule(),
                Rule::notIn(['server-only-denied-name']),
            ],
            'occurred_on' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
