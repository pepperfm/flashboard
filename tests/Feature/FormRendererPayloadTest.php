<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Pepperfm\Flashboard\Contracts\Forms\FieldRenderer;
use Pepperfm\Flashboard\Core\Forms\Builders\Form;
use Pepperfm\Flashboard\Core\Forms\Fields\Select;
use Pepperfm\Flashboard\Core\Forms\Fields\TextInput;
use Pepperfm\Flashboard\Core\Forms\Fields\Toggle;
use Pepperfm\Flashboard\Core\Forms\Layout\Section;
use Pepperfm\Flashboard\Core\Forms\Layout\Tab;
use Pepperfm\Flashboard\Core\Runtime\Payloads\FormPayload;
use Pepperfm\Flashboard\Tests\TestCase;

final class FormRendererPayloadTest extends TestCase
{
    public function test_typed_fields_reject_unknown_explicit_renderer_values(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown form field renderer [texarea] for field [notes].');

        TextInput::make('notes')->renderer('texarea');
    }

    public function test_legacy_array_fields_reject_unknown_explicit_renderer_values(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown form field renderer [texarea] for field [notes].');

        Form::make()
            ->schema([
                ['key' => 'notes', 'label' => 'Notes', 'type' => 'text', 'renderer' => 'texarea'],
            ])
            ->toArray();
    }

    public function test_simple_form_payload_exposes_renderer_hints_for_base_wrappers(): void
    {
        $payload = new FormPayload(
            Form::make()
                ->schema([
                    TextInput::make('title')->label('Title')->required(),
                    TextInput::make('notes')
                        ->label('Notes')
                        ->renderer(FieldRenderer::Textarea),
                    Select::make('status')->label('Status')->options(['draft' => 'Draft']),
                    Toggle::make('is_active')->label('Is active'),
                ])
                ->toArray(),
        );

        self::assertSame(
            [
                'title' => FieldRenderer::Input->value,
                'notes' => FieldRenderer::Textarea->value,
                'status' => FieldRenderer::Select->value,
                'is_active' => FieldRenderer::Switch->value,
            ],
            array_column($payload->fields(), 'renderer', 'key'),
        );
    }

    public function test_grouped_form_payload_keeps_renderer_hints_inside_sections_and_tabs(): void
    {
        $payload = new FormPayload(
            Form::make()
                ->sections([
                    Section::make('content')->label('Content')->schema([
                        TextInput::make('summary')
                            ->label('Summary')
                            ->renderer(FieldRenderer::Textarea),
                    ]),
                ])
                ->tabs([
                    Tab::make('settings')->label('Settings')->schema([
                        Toggle::make('is_active')->label('Is active'),
                    ]),
                ])
                ->toArray(),
        );

        self::assertSame(
            ['summary' => FieldRenderer::Textarea->value],
            array_column($payload->sections()[0]['schema'], 'renderer', 'key'),
        );
        self::assertSame(
            ['is_active' => FieldRenderer::Switch->value],
            array_column($payload->tabs()[0]['schema'], 'renderer', 'key'),
        );
    }
}
