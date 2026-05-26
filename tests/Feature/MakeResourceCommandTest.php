<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Pepperfm\Flashboard\Integration\Laravel\Console\MakeResourceCommand;
use Pepperfm\Flashboard\Tests\TestCase;

final class MakeResourceCommandTest extends TestCase
{
    public function test_default_resource_class_is_inferred_from_model_class(): void
    {
        $reflection = new \ReflectionClass(MakeResourceCommand::class);
        $method = $reflection->getMethod('defaultResourceClassForModelClass');
        $method->setAccessible(true);
        $command = $reflection->newInstanceWithoutConstructor();

        self::assertSame(
            'OrdersResource',
            $method->invoke($command, 'App\\Models\\Order'),
        );
        self::assertSame(
            'ProductCategoriesResource',
            $method->invoke($command, 'App\\Models\\ProductCategory'),
        );
    }

    public function test_model_fqcn_can_be_passed_as_the_only_argument(): void
    {
        $reflection = new \ReflectionClass(MakeResourceCommand::class);
        $method = $reflection->getMethod('looksLikeModelClass');
        $method->setAccessible(true);
        $command = $reflection->newInstanceWithoutConstructor();

        self::assertTrue($method->invoke($command, 'App\\Models\\Order'));
        self::assertFalse($method->invoke($command, 'OrdersResource'));
    }

    public function test_render_stub_can_scaffold_minimal_id_only_resource(): void
    {
        $content = $this->renderResourceStub(
            titleField: 'id',
            secondaryField: '',
            includeDetail: false,
        );

        self::assertStringContainsString(
            "                TextColumn::make('id')" . PHP_EOL
            . "                    ->label('ID')" . PHP_EOL
            . "                    ->sortable()," . PHP_EOL
            . '            ])',
            $content,
        );
        self::assertStringContainsString(
            "                TextInput::make('id')" . PHP_EOL
            . "                    ->label('ID')" . PHP_EOL
            . '                    ->required(),',
            $content,
        );
        self::assertSame(1, substr_count($content, "TextColumn::make('id')"));
        self::assertStringContainsString(
            "    public static function navigationIcon(): string" . PHP_EOL
            . "    {" . PHP_EOL
            . "        return 'lucide:panel-left';" . PHP_EOL
            . "    }",
            $content,
        );
        self::assertStringNotContainsString('email', $content);
        self::assertStringNotContainsString('public static function detail(', $content);
        self::assertStringNotContainsString('{{ title_field }}', $content);
        self::assertStringContainsString("'id' => ['required', 'string'],", $content);
        self::assertStringContainsString('use Pepperfm\\Flashboard\\Core\\Tables\\Actions\\DeleteAction;', $content);
        self::assertStringContainsString('use Pepperfm\\Flashboard\\Core\\Tables\\Actions\\EditAction;', $content);
        self::assertStringNotContainsString('->actions(', $content);
        self::assertStringContainsString('public static function actions(): array', $content);
        self::assertStringContainsString(
            "            EditAction::make()," . PHP_EOL
            . "            DeleteAction::make(),",
            $content,
        );
        self::assertStringNotContainsString(PHP_EOL . PHP_EOL . PHP_EOL, $content);
    }

    public function test_render_stub_does_not_add_extra_blank_lines_when_optional_sections_are_enabled(): void
    {
        $content = $this->renderResourceStub(
            titleField: 'name',
            secondaryField: 'email',
            includeDetail: true,
        );

        self::assertStringContainsString(
            "                TextColumn::make('name')" . PHP_EOL
            . "                    ->label('Name')" . PHP_EOL
            . "                    ->sortable()" . PHP_EOL
            . "                    ->searchable()," . PHP_EOL
            . "                TextColumn::make('email')" . PHP_EOL
            . "                    ->label('Email')" . PHP_EOL
            . "                    ->searchable()," . PHP_EOL
            . '            ])',
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
        self::assertStringContainsString('public static function actions(): array', $content);
        self::assertStringContainsString('public static function form(FormContract $form): FormContract', $content);
        self::assertStringContainsString('->schema([', $content);
        self::assertStringNotContainsString('Section::make(', $content);
        self::assertStringContainsString(
            '    }' . PHP_EOL . PHP_EOL . '    public static function table(TableContract $table): TableContract',
            $content,
        );
        self::assertStringContainsString(
            "                TextColumn::make('name')" . PHP_EOL
            . "                    ->label('Name')" . PHP_EOL
            . "                    ->sortable()" . PHP_EOL
            . "                    ->searchable()," . PHP_EOL
            . '            ])',
            $content,
        );
        self::assertStringContainsString("TextInput::make('name')", $content);
        self::assertStringContainsString("->label('Name')", $content);
        self::assertStringContainsString("->required(),", $content);
        self::assertStringNotContainsString(PHP_EOL . PHP_EOL . PHP_EOL, $content);
    }

    public function test_render_stub_marks_notes_like_secondary_fields_with_textarea_field(): void
    {
        $content = $this->renderResourceStub(
            titleField: 'name',
            secondaryField: 'notes',
            includeDetail: false,
        );

        self::assertStringContainsString('use Pepperfm\\Flashboard\\Core\\Forms\\Fields\\Textarea;', $content);
        self::assertStringNotContainsString('use Pepperfm\\Flashboard\\Contracts\\Forms\\FieldRenderer;', $content);
        self::assertStringContainsString(
            "                Textarea::make('notes')" . PHP_EOL
            . "                    ->label('Notes'),",
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
