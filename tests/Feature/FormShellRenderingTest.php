<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Pepperfm\Flashboard\Tests\TestCase;

final class FormShellRenderingTest extends TestCase
{
    public function test_screen_content_delegates_form_pages_to_dedicated_shell_components(): void
    {
        $content = $this->fixture('resources/js/components/flashboard/FlashboardScreenContent.vue');

        self::assertStringContainsString('import SimpleFormShell', $content);
        self::assertStringContainsString('<SimpleFormShell', $content);
        self::assertStringNotContainsString('import SectionedFormShell', $content);
        self::assertStringNotContainsString('import TabbedFormShell', $content);
        self::assertStringContainsString(':schema="formSchema"', $content);
        self::assertStringNotContainsString('hasTabbedFormLayout', $content);
        self::assertStringNotContainsString('hasSectionedFormLayout', $content);
    }

    public function test_simple_form_shell_uses_centered_page_card_composition(): void
    {
        $content = $this->fixture('resources/js/components/flashboard/forms/layout/SimpleFormShell.vue');

        self::assertStringContainsString('<UPageSection', $content);
        self::assertStringContainsString('<UPageCard', $content);
        self::assertStringContainsString('<FormNodeRenderer', $content);
        self::assertStringContainsString('SIMPLE_FORM_DEFAULT_LAYOUT', $content);
    }

    public function test_grouped_shells_render_through_shared_form_layout_layer(): void
    {
        $layoutResolver = $this->fixture('resources/js/components/flashboard/forms/layout/resolveFormLayout.ts');
        $containerRenderer = $this->fixture('resources/js/components/flashboard/forms/renderers/FormContainerRenderer.vue');
        $nodeRenderer = $this->fixture('resources/js/components/flashboard/forms/renderers/FormNodeRenderer.vue');

        self::assertStringContainsString('resolveFormContainerLayout', $layoutResolver);
        self::assertStringContainsString('resolveFormItemLayout', $layoutResolver);
        self::assertStringContainsString('<UTabs', $containerRenderer);
        self::assertStringContainsString('<FormNodeRenderer', $containerRenderer);
        self::assertStringContainsString('<FormContainerRenderer', $nodeRenderer);
    }

    private function fixture(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }
}
