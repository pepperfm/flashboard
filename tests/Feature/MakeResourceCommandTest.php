<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Pepperfm\Flashboard\Integration\Laravel\Console\MakeResourceCommand;
use Pepperfm\Flashboard\Tests\TestCase;

final class MakeResourceCommandTest extends TestCase
{
    public function test_render_stub_does_not_add_extra_blank_lines_when_optional_sections_are_enabled(): void
    {
        $content = $this->renderResourceStub(
            titleField: 'name',
            secondaryField: 'email',
            includeDetail: true,
        );

        self::assertStringContainsString(
            "            TextColumn::make('name')" . PHP_EOL
            . "                ->label('Name')" . PHP_EOL
            . "                ->sortable()" . PHP_EOL
            . "                ->searchable()," . PHP_EOL
            . "            TextColumn::make('email')" . PHP_EOL
            . "                ->label('Email')" . PHP_EOL
            . "                ->searchable()," . PHP_EOL
            . '        ]);',
            $content,
        );
        self::assertStringContainsString(
            "        return \$detail->entries([" . PHP_EOL
            . "            TextEntry::make('id')" . PHP_EOL
            . "                ->label('ID')," . PHP_EOL
            . "            TextEntry::make('name')" . PHP_EOL
            . "                ->label('Name')," . PHP_EOL
            . "            TextEntry::make('email')" . PHP_EOL
            . "                ->label('Email')," . PHP_EOL
            . '        ]);',
            $content,
        );
        self::assertStringContainsString(
            '    }' . PHP_EOL . PHP_EOL . '    public static function form(FormContract $form): FormContract',
            $content,
        );
        self::assertStringNotContainsString(PHP_EOL . PHP_EOL . PHP_EOL, $content);
    }

    public function test_render_stub_does_not_leave_empty_sections_when_optional_sections_are_disabled(): void
    {
        $content = $this->renderResourceStub(
            titleField: 'name',
            secondaryField: '',
            includeDetail: false,
        );

        self::assertStringNotContainsString('public static function navigationGroup()', $content);
        self::assertStringNotContainsString('public static function detail(', $content);
        self::assertStringNotContainsString('public static function actions()', $content);
        self::assertStringContainsString('public static function form(FormContract $form): FormContract', $content);
        self::assertStringContainsString('->schema([', $content);
        self::assertStringNotContainsString('Section::make(', $content);
        self::assertStringContainsString(
            '    }' . PHP_EOL . PHP_EOL . '    public static function table(TableContract $table): TableContract',
            $content,
        );
        self::assertStringContainsString(
            "            TextColumn::make('name')" . PHP_EOL
            . "                ->label('Name')" . PHP_EOL
            . "                ->sortable()" . PHP_EOL
            . "                ->searchable()," . PHP_EOL
            . '        ]);',
            $content,
        );
        self::assertStringContainsString("TextInput::make('name')", $content);
        self::assertStringContainsString("->label('Name')", $content);
        self::assertStringContainsString("->required(),", $content);
        self::assertStringNotContainsString(PHP_EOL . PHP_EOL . PHP_EOL, $content);
    }

    public function test_render_stub_marks_notes_like_secondary_fields_with_explicit_textarea_renderer(): void
    {
        $content = $this->renderResourceStub(
            titleField: 'name',
            secondaryField: 'notes',
            includeDetail: false,
        );

        self::assertStringContainsString('use Pepperfm\\Flashboard\\Contracts\\Forms\\FieldRenderer;', $content);
        self::assertStringContainsString(
            "                TextInput::make('notes')" . PHP_EOL
            . "                    ->label('Notes')" . PHP_EOL
            . '                    ->renderer(FieldRenderer::Textarea),',
            $content,
        );
    }

    private function renderResourceStub(
        string $titleField,
        string $secondaryField,
        bool $includeDetail,
    ): string {
        $reflection = new \ReflectionClass(MakeResourceCommand::class);
        $method = $reflection->getMethod('renderStub');
        $method->setAccessible(true);
        $command = $reflection->newInstanceWithoutConstructor();

        return (string) $method->invoke(
            $command,
            file_get_contents(dirname(__DIR__, 2) . '/stubs/resource.stub'),
            'UsersResource',
            'App\\Models\\User',
            $titleField,
            $secondaryField,
            '',
            $includeDetail,
        );
    }
}
