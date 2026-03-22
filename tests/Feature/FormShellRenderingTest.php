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
        self::assertStringContainsString('import SectionedFormShell', $content);
        self::assertStringContainsString('import TabbedFormShell', $content);
        self::assertStringContainsString('<SimpleFormShell', $content);
        self::assertStringContainsString('<SectionedFormShell', $content);
        self::assertStringContainsString('<TabbedFormShell', $content);
        self::assertStringNotContainsString('function fieldComponent(', $content);
        self::assertStringNotContainsString('<UInput', $content);
        self::assertStringNotContainsString('<UTextarea', $content);
        self::assertStringNotContainsString('<USelect', $content);
        self::assertStringNotContainsString('<USwitch', $content);
        self::assertStringNotContainsString('<component', $content);
    }

    public function test_simple_form_shell_uses_centered_page_card_composition(): void
    {
        $content = $this->fixture('resources/js/components/flashboard/forms/layout/SimpleFormShell.vue');

        self::assertStringContainsString('<UPageSection', $content);
        self::assertStringContainsString('<UPageCard', $content);
        self::assertStringContainsString('<FormFieldRenderer', $content);
        self::assertStringContainsString("class=\"simple-field-stack\"", $content);
    }

    public function test_grouped_shells_render_through_shared_form_field_renderer(): void
    {
        $sectioned = $this->fixture('resources/js/components/flashboard/forms/layout/SectionedFormShell.vue');
        $tabbed = $this->fixture('resources/js/components/flashboard/forms/layout/TabbedFormShell.vue');

        self::assertStringContainsString('<FormFieldRenderer', $sectioned);
        self::assertStringContainsString('<UCard variant="soft">', $sectioned);
        self::assertStringContainsString('<FormFieldRenderer', $tabbed);
        self::assertStringContainsString('<UTabs', $tabbed);
    }

    private function fixture(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }
}
