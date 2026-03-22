<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Pepperfm\Flashboard\Contracts\Forms\FormContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Tests\TestCase;

final class ResourceFormRulesTest extends TestCase
{
    public function test_creation_and_update_rules_fall_back_to_form_builder_rules(): void
    {
        $resourceClass = new class() extends Resource
        {
            public static function model(): string
            {
                return \Illuminate\Database\Eloquent\Model::class;
            }

            public static function form(FormContract $form): FormContract
            {
                return $form
                    ->schema([
                        ['key' => 'email', 'label' => 'Email'],
                    ])
                    ->rules([
                        'email' => ['required', 'string'],
                    ]);
            }
        };

        self::assertSame(
            ['email' => ['required', 'string']],
            $resourceClass::creationRules(),
        );
        $record = new class() extends \Illuminate\Database\Eloquent\Model
        {
        };
        self::assertSame(
            ['email' => ['required', 'string']],
            $resourceClass::updateRules($record),
        );
    }
}
