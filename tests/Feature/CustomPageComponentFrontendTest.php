<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Pepperfm\Flashboard\Tests\TestCase;

final class CustomPageComponentFrontendTest extends TestCase
{
    public function test_flashboard_entrypoint_installs_page_component_runtime(): void
    {
        $entrypoint = $this->fixture('resources/js/flashboard.ts');
        $registry = $this->fixture('resources/js/components/flashboard/custom-pages/pageComponentRegistry.ts');
        $types = $this->fixture('resources/js/inertia.d.ts');

        self::assertStringContainsString('installFlashboardRuntime()', $entrypoint);
        self::assertStringContainsString('registerPageComponent', $registry);
        self::assertStringContainsString('resolvePageComponent', $registry);
        self::assertStringContainsString('window.Flashboard', $registry);
        self::assertStringContainsString('interface Window', $types);
        self::assertStringContainsString('Flashboard?', $types);
        self::assertStringNotContainsString('console.', $registry);
    }

    public function test_screen_content_delegates_component_pages_to_custom_renderer(): void
    {
        $screen = $this->fixture('resources/js/components/flashboard/FlashboardScreenContent.vue');
        $renderer = $this->fixture('resources/js/components/flashboard/custom-pages/FBCustomPageRenderer.vue');

        self::assertStringContainsString('import FBCustomPageRenderer', $screen);
        self::assertStringContainsString('payload.component?.name', $screen);
        self::assertStringContainsString('<FBCustomPageRenderer', $screen);
        self::assertStringContainsString(':component="payload.component"', $screen);
        self::assertStringContainsString('loadPageComponentAssets', $renderer);
        self::assertStringContainsString('resolvePageComponent', $renderer);
        self::assertStringContainsString('<component', $renderer);
        self::assertStringNotContainsString('console.', $renderer);
    }

    public function test_component_asset_loader_appends_page_scripts_and_styles_once(): void
    {
        $loader = $this->fixture('resources/js/components/flashboard/custom-pages/pageAssets.ts');

        self::assertStringContainsString("document.createElement('script')", $loader);
        self::assertStringContainsString("document.createElement('link')", $loader);
        self::assertStringContainsString('loadedScripts', $loader);
        self::assertStringContainsString('loadedStyles', $loader);
        self::assertStringContainsString('loadingScripts', $loader);
        self::assertStringContainsString('loadingStyles', $loader);
        self::assertStringContainsString('Failed to load custom page style asset.', $loader);
        self::assertStringNotContainsString('console.', $loader);
    }

    private function fixture(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }
}
