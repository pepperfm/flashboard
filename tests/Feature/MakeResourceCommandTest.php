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
            includeForm: true,
            includeDetail: true,
            includeActions: true,
        );

        self::assertStringContainsString(
            "            TextColumn::make('name')->label('Name')->sortable()->searchable()," . PHP_EOL
            . "            TextColumn::make('email')->label('Email')->searchable()," . PHP_EOL
            . '        ]);',
            $content,
        );
        self::assertStringContainsString(
            "        return \$detail->entries([" . PHP_EOL
            . "            TextEntry::make('id')->label('ID')," . PHP_EOL
            . "            TextEntry::make('name')->label('Name')," . PHP_EOL
            . "            TextEntry::make('email')->label('Email')," . PHP_EOL
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
            includeForm: false,
            includeDetail: false,
            includeActions: false,
        );

        self::assertStringNotContainsString('public static function navigationGroup()', $content);
        self::assertStringNotContainsString('public static function form(', $content);
        self::assertStringNotContainsString('public static function detail(', $content);
        self::assertStringNotContainsString('public static function actions()', $content);
        self::assertStringContainsString(
            '    }' . PHP_EOL . PHP_EOL . '    public static function table(TableContract $table): TableContract',
            $content,
        );
        self::assertStringContainsString(
            "            TextColumn::make('name')->label('Name')->sortable()->searchable()," . PHP_EOL
            . '        ]);' . PHP_EOL
            . '    }' . PHP_EOL
            . '}',
            $content,
        );
        self::assertStringNotContainsString(PHP_EOL . PHP_EOL . PHP_EOL, $content);
    }

    private function renderResourceStub(
        string $titleField,
        string $secondaryField,
        bool $includeForm,
        bool $includeDetail,
        bool $includeActions,
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
            $includeForm,
            $includeDetail,
            $includeActions,
        );
    }
}
