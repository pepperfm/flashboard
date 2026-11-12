<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Pepperfm\Flashboard\Integration\Laravel\Console\MakePageCommand;
use Pepperfm\Flashboard\Tests\TestCase;

final class MakePageCommandTest extends TestCase
{
    public function test_render_stub_can_scaffold_workspace_only_page(): void
    {
        $content = $this->renderPageStub(componentName: null);

        self::assertStringContainsString('final class ReviewQueuePage extends CustomPage', $content);
        self::assertStringContainsString("return 'Review Queue';", $content);
        self::assertStringContainsString("return 'queues/review';", $content);
        self::assertStringContainsString("return 'Review incoming submissions';", $content);
        self::assertStringContainsString('public static function workspaceDescription(): ?string', $content);
        self::assertStringNotContainsString('public static function component()', $content);
        self::assertStringNotContainsString('{{ component_methods }}', $content);
        self::assertStringNotContainsString(PHP_EOL . PHP_EOL . PHP_EOL, $content);
    }

    public function test_render_stub_can_scaffold_component_page_methods(): void
    {
        $content = $this->renderPageStub(componentName: 'review-queue');

        self::assertStringContainsString('public static function component(): ?string', $content);
        self::assertStringContainsString("return 'review-queue';", $content);
        self::assertStringContainsString('public static function componentProps(array $context = []): array', $content);
        self::assertStringContainsString("'initialFilters' => \$context['query'] ?? [],", $content);
        self::assertStringContainsString('public static function componentAssets(): array', $content);
        self::assertStringContainsString("'url' => app(\\Illuminate\\Foundation\\Vite::class)->asset('resources/js/flashboard/pages.ts')", $content);
        self::assertStringNotContainsString("'/build/flashboard-pages.js'", $content);
        self::assertStringContainsString('public static function workspaceDescription(): ?string', $content);
        self::assertStringNotContainsString('{{ component_methods }}', $content);
        self::assertStringNotContainsString(PHP_EOL . PHP_EOL . PHP_EOL, $content);
    }

    public function test_component_name_is_inferred_from_page_class(): void
    {
        $reflection = new \ReflectionClass(MakePageCommand::class);
        $defaultName = $reflection->getMethod('defaultComponentName');
        $defaultName->setAccessible(true);
        $validName = $reflection->getMethod('isValidComponentName');
        $validName->setAccessible(true);
        $command = $reflection->newInstanceWithoutConstructor();

        self::assertSame('review-queue', $defaultName->invoke($command, 'ReviewQueuePage'));
        self::assertTrue($validName->invoke($command, 'review-queue'));
        self::assertFalse($validName->invoke($command, 'review queue'));
    }

    public function test_component_entry_update_is_idempotent(): void
    {
        $filesystem = new Filesystem();
        $directory = sys_get_temp_dir() . '/flashboard-make-page-' . bin2hex(random_bytes(6));
        $entryPath = $directory . '/pages.ts';

        try {
            $reflection = new \ReflectionClass(MakePageCommand::class);
            $method = $reflection->getMethod('writeComponentEntry');
            $method->setAccessible(true);
            $command = $reflection->newInstanceWithoutConstructor();

            $filesystem->ensureDirectoryExists($directory);

            $method->invoke($command, $filesystem, $entryPath, 'ReviewQueuePage', 'review-queue');
            $method->invoke($command, $filesystem, $entryPath, 'ReviewQueuePage', 'review-queue');

            $content = $filesystem->get($entryPath);

            self::assertSame(1, substr_count($content, "import ReviewQueuePage from './pages/ReviewQueuePage.vue'"));
            self::assertSame(1, substr_count($content, "window.Flashboard?.registerPageComponent?.('review-queue', ReviewQueuePage)"));
            self::assertStringNotContainsString('console.', $content);
        } finally {
            if ($filesystem->exists($directory)) {
                $filesystem->deleteDirectory($directory);
            }
        }
    }

    public function test_component_stubs_use_browser_registry_without_logging(): void
    {
        $entryStub = $this->fixture('stubs/page-components-entry.ts.stub');
        $componentStub = $this->fixture('stubs/page-component.vue.stub');

        self::assertStringContainsString('window.Flashboard?.registerPageComponent?', $entryStub);
        self::assertStringContainsString('defineProps', $componentStub);
        self::assertStringNotContainsString('console.', $entryStub);
        self::assertStringNotContainsString('console.', $componentStub);
    }

    private function renderPageStub(?string $componentName): string
    {
        $reflection = new \ReflectionClass(MakePageCommand::class);
        $method = $reflection->getMethod('renderStub');
        $method->setAccessible(true);
        $command = $reflection->newInstanceWithoutConstructor();

        return (string) $method->invoke(
            $command,
            $this->fixture('stubs/page.stub'),
            'ReviewQueuePage',
            'Review Queue',
            'queues/review',
            'Review incoming submissions',
            $componentName,
        );
    }

    private function fixture(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }
}
