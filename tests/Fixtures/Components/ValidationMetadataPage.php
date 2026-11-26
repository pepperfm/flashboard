<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Fixtures\Components;

use Pepperfm\Flashboard\Contracts\Pages\PageType;
use Pepperfm\Flashboard\Core\Forms\Builders\Form;
use Pepperfm\Flashboard\Core\Pages\CustomPage;
use Pepperfm\Flashboard\Core\Runtime\Payloads\FormPayload;
use Pepperfm\Flashboard\Tests\Fixtures\Resources\ValidationProbeResource;

final class ValidationMetadataPage extends CustomPage
{
    public static function title(): string
    {
        return 'Validation metadata';
    }

    public static function type(): PageType
    {
        return PageType::Custom;
    }

    public static function uri(): string
    {
        return 'validation-metadata';
    }

    public static function component(): string
    {
        return 'validation-metadata';
    }

    public static function componentProps(array $context = []): array
    {
        return ['form' => self::publicForm()];
    }

    public static function workspace(array $context = []): array
    {
        return [...parent::workspace($context), 'form' => self::publicForm()];
    }

    private static function publicForm(): array
    {
        return new FormPayload(ValidationProbeResource::form(Form::make())->toArray())->toArray();
    }
}
