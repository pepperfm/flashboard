<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Pepperfm\Flashboard\Contracts\Forms\FieldRenderer;
use Pepperfm\Flashboard\Contracts\Forms\FormLayoutAlign;
use Pepperfm\Flashboard\Contracts\Forms\FormLayoutDirection;
use Pepperfm\Flashboard\Contracts\Forms\FormLayoutJustify;
use Pepperfm\Flashboard\Contracts\Forms\FormLayoutMode;
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

    public function test_form_layout_normalization_supports_grid_flex_and_field_spans(): void
    {
        $payload = new FormPayload(
            Form::make()
                ->columns(3)
                ->gap([
                    'default' => 4,
                    'lg' => 6,
                ])
                ->schema([
                    TextInput::make('title')->label('Title')->columnSpan(2),
                    TextInput::make('summary')->label('Summary')->fullWidth(),
                ])
                ->sections([
                    Section::make('filters')
                        ->label('Filters')
                        ->layout(FormLayoutMode::Flex)
                        ->direction(FormLayoutDirection::Row)
                        ->justify(FormLayoutJustify::Between)
                        ->align(FormLayoutAlign::Center)
                        ->wrap(false)
                        ->gap(2)
                        ->schema([
                            Select::make('status')->label('Status'),
                            Toggle::make('is_active')->label('Is active'),
                        ]),
                ])
                ->tabs([
                    Tab::make('details')
                        ->label('Details')
                        ->columns([
                            'default' => 1,
                            'lg' => 2,
                        ])
                        ->schema([
                            TextInput::make('notes')->label('Notes')->fullWidth(),
                        ]),
                ])
                ->toArray(),
        );

        self::assertSame(
            [
                'mode' => 'grid',
                'gap' => ['default' => 4, 'lg' => 6],
                'columns' => ['default' => 1, 'md' => 3],
            ],
            $payload->toArray()['layout'],
        );
        self::assertSame(
            ['column_span' => 2],
            $payload->fields()[0]['layout'],
        );
        self::assertSame(
            ['column_span' => 'full'],
            $payload->fields()[1]['layout'],
        );
        self::assertSame(
            [
                'mode' => 'flex',
                'gap' => ['default' => 2],
                'direction' => 'row',
                'justify' => 'between',
                'align' => 'center',
                'wrap' => false,
            ],
            $payload->sections()[0]['layout'],
        );
        self::assertSame(
            [
                'mode' => 'grid',
                'columns' => ['default' => 1, 'lg' => 2],
            ],
            $payload->tabs()[0]['layout'],
        );
    }

    public function test_form_layout_normalization_rejects_mixed_grid_and_flex_configuration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Form layouts cannot mix grid columns with flex-specific settings.');

        Form::make()
            ->columns(2)
            ->direction(FormLayoutDirection::Row)
            ->toArray();
    }

    public function test_form_layout_normalization_rejects_unknown_breakpoints(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown form layout breakpoint [tablet].');

        Form::make()
            ->sections([
                Section::make('content')->schema([
                    ['key' => 'title', 'type' => 'text', 'column_span' => ['tablet' => 2]],
                ]),
            ])
            ->toArray();
    }

    public function test_form_layout_normalization_rejects_unknown_layout_modes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown form layout mode [masonry].');

        Form::make()
            ->layout('masonry')
            ->toArray();
    }

    public function test_form_layout_normalization_rejects_unknown_flex_directions(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown form layout direction [inline].');

        Form::make()
            ->layout(FormLayoutMode::Flex)
            ->direction('inline')
            ->toArray();
    }

    public function test_legacy_and_typed_form_layout_definitions_normalize_to_the_same_payload_shape(): void
    {
        $typedPayload = new FormPayload(
            Form::make()
                ->columns(2)
                ->schema([
                    TextInput::make('name')->label('Name'),
                    TextInput::make('email')->label('Email')->columnSpan(2),
                ])
                ->sections([
                    Section::make('meta')
                        ->label('Meta')
                        ->layout(FormLayoutMode::Flex)
                        ->direction(FormLayoutDirection::Row)
                        ->gap(2)
                        ->schema([
                            Toggle::make('is_active')->label('Is active'),
                        ]),
                ])
                ->toArray(),
        );

        $arrayPayload = new FormPayload(
            Form::make()
                ->sections([
                    [
                        'key' => 'meta',
                        'label' => 'Meta',
                        'layout' => 'flex',
                        'direction' => 'row',
                        'gap' => 2,
                        'schema' => [
                            ['key' => 'is_active', 'label' => 'Is active', 'type' => 'toggle'],
                        ],
                    ],
                ])
                ->schema([
                    ['key' => 'name', 'label' => 'Name', 'type' => 'text'],
                    ['key' => 'email', 'label' => 'Email', 'type' => 'text', 'column_span' => 2],
                ])
                ->columns(2)
                ->toArray(),
        );

        self::assertSame($typedPayload->toArray()['layout'], $arrayPayload->toArray()['layout']);
        self::assertSame($typedPayload->fields()[1]['layout'], $arrayPayload->fields()[1]['layout']);
        self::assertSame($typedPayload->sections()[0]['layout'], $arrayPayload->sections()[0]['layout']);
    }
}
