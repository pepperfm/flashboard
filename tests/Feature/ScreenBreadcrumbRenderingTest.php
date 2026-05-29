<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Pepperfm\Flashboard\Tests\TestCase;

final class ScreenBreadcrumbRenderingTest extends TestCase
{
    public function test_screen_breadcrumbs_are_adapted_for_nuxt_ui_links(): void
    {
        $screen = $this->fixture('resources/js/Pages/Flashboard/Screen.vue');
        $content = $this->fixture('resources/js/components/flashboard/FlashboardScreenContent.vue');
        $layoutFactory = $this->fixture('src/UI/Layout/PanelLayoutFactory.php');

        self::assertStringContainsString('type LayoutBreadcrumb', $screen);
        self::assertStringContainsString('normalizeBreadcrumb', $screen);
        self::assertStringContainsString('item.to ?? item.href', $screen);
        self::assertStringContainsString("normalizedTarget === '' || normalizedTarget === '#'", $screen);
        self::assertStringContainsString("to?: string", $content);
        self::assertStringContainsString('<UBreadcrumb :items="breadcrumbs" />', $content);
        self::assertStringContainsString('$this->panelHref($metadata)', $layoutFactory);
        self::assertStringContainsString("'panel_path'", $layoutFactory);
    }

    private function fixture(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }
}
