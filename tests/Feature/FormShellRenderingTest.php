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

    public function test_renderer_map_registers_advanced_field_wrappers(): void
    {
        $map = $this->fixture('resources/js/components/flashboard/forms/renderers/FormFieldRendererMap.ts');
        $resolver = $this->fixture('resources/js/components/flashboard/forms/renderers/resolveFormFieldRenderer.ts');
        $screen = $this->fixture('resources/js/components/flashboard/FlashboardScreenContent.vue');

        self::assertStringContainsString('FBDateInput', $map);
        self::assertStringContainsString('FBFileUpload', $map);
        self::assertStringContainsString('FBRelationMultiSelect', $map);
        self::assertStringContainsString('FBRelationSelect', $map);
        self::assertStringContainsString('FBRichText', $map);
        self::assertStringContainsString('date: FBDateInput', $map);
        self::assertStringContainsString('file_upload: FBFileUpload', $map);
        self::assertStringContainsString('relation_multi_select: FBRelationMultiSelect', $map);
        self::assertStringContainsString('relation_select: FBRelationSelect', $map);
        self::assertStringContainsString('rich_text: FBRichText', $map);

        self::assertStringContainsString("return 'date'", $resolver);
        self::assertStringContainsString("return 'file_upload'", $resolver);
        self::assertStringContainsString("return 'relation_multi_select'", $resolver);
        self::assertStringContainsString("return 'relation_select'", $resolver);
        self::assertStringContainsString("return 'rich_text'", $resolver);
        self::assertStringContainsString('selectedOptions: field.selected_options ?? []', $resolver);
        self::assertStringContainsString('maxItems: field.max_items', $resolver);
        self::assertStringContainsString('forceFormData', $screen);
        self::assertStringContainsString("_method: 'put'", $screen);
        self::assertStringContainsString('update:remove-value', $this->fixture('resources/js/components/flashboard/forms/renderers/FormFieldRenderer.vue'));
        self::assertStringContainsString('Existing file will be removed', $this->fixture('resources/js/components/flashboard/forms/fields/FBFileUpload.vue'));
        self::assertStringContainsString('url: option.url ?? existingOption?.url', $this->fixture('resources/js/components/flashboard/forms/fields/FBRelationSelect.vue'));
        self::assertStringContainsString("url.searchParams.append('selected[]'", $this->fixture('resources/js/components/flashboard/forms/fields/FBRelationMultiSelect.vue'));
        self::assertStringContainsString('multiple', $this->fixture('resources/js/components/flashboard/forms/fields/FBRelationMultiSelect.vue'));
        self::assertStringNotContainsString('console.', $this->fixture('resources/js/components/flashboard/forms/fields/FBRelationSelect.vue'));
        self::assertStringNotContainsString('console.', $this->fixture('resources/js/components/flashboard/forms/fields/FBRelationMultiSelect.vue'));
    }

    public function test_form_submit_path_preserves_array_field_values(): void
    {
        $screen = $this->fixture('resources/js/components/flashboard/FlashboardScreenContent.vue');
        $renderer = $this->fixture('resources/js/components/flashboard/forms/renderers/FormFieldRenderer.vue');
        $multiSelect = $this->fixture('resources/js/components/flashboard/forms/fields/FBRelationMultiSelect.vue');

        self::assertStringContainsString('function updateFieldValue(fieldKey: string, value: unknown)', $screen);
        self::assertStringContainsString('form[fieldKey] = value', $screen);
        self::assertStringContainsString('@update:model-value="emit(\'update:modelValue\', $event)"', $renderer);
        self::assertStringContainsString("'update:modelValue': [value: RelationOptionValue[]]", $multiSelect);
        self::assertStringNotContainsString('JSON.stringify(form', $screen);
        self::assertStringNotContainsString('Object.fromEntries', $screen);
    }

    public function test_screen_content_does_not_render_breadcrumbs(): void
    {
        $screenPage = $this->fixture('resources/js/Pages/Flashboard/Screen.vue');
        $screenContent = $this->fixture('resources/js/components/flashboard/FlashboardScreenContent.vue');

        self::assertStringNotContainsString('breadcrumbs', $screenPage);
        self::assertStringNotContainsString('UBreadcrumb', $screenContent);
        self::assertStringNotContainsString('screen-breadcrumbs', $screenContent);
    }

    private function fixture(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }
}
