<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Fixtures\Resources;

use Pepperfm\Flashboard\Contracts\Forms\FormContract;
use Pepperfm\Flashboard\Core\Forms\Fields\TextInput;

final class ValidationOverrideProbeResource extends ValidationProbeResource
{
    public static int $ruleCalls = 0;

    public static function key(): string
    {
        return 'validation_override_probe';
    }

    public static function form(FormContract $form): FormContract
    {
        return $form->schema([
            TextInput::make('name')->required(),
            TextInput::make('occurred_on')->required(),
        ]);
    }

    public static function formRules(?\Illuminate\Database\Eloquent\Model $record = null): array
    {
        ++self::$ruleCalls;

        $rules = self::serverRules();
        $unique = \Illuminate\Validation\Rule::unique('ai_probe_records', 'name');
        if ($record !== null) {
            $unique->ignore($record->getKey());
        }
        $rules['name'][] = $unique;

        return $rules;
    }
}
