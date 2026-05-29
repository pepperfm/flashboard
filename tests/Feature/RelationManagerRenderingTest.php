<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Pepperfm\Flashboard\Tests\TestCase;

final class RelationManagerRenderingTest extends TestCase
{
    public function test_screen_content_registers_relation_manager_components(): void
    {
        $content = $this->fixture('resources/js/components/flashboard/FlashboardScreenContent.vue');

        self::assertStringContainsString('FBHasOneRelationManager', $content);
        self::assertStringContainsString('FBHasManyRelationManager', $content);
        self::assertStringContainsString('screenRelationManagers', $content);
        self::assertStringContainsString('detailRelationManagers', $content);
        self::assertStringContainsString('legacyDetailRelations', $content);
    }

    public function test_relation_manager_components_use_nuxt_ui_and_have_no_console_logging(): void
    {
        $hasOne = $this->fixture('resources/js/components/flashboard/relations/FBHasOneRelationManager.vue');
        $hasMany = $this->fixture('resources/js/components/flashboard/relations/FBHasManyRelationManager.vue');
        $confirm = $this->fixture('resources/js/components/flashboard/relations/RelationConfirmButton.vue');
        $requests = $this->fixture('resources/js/components/flashboard/relations/relationRequests.ts');

        self::assertStringContainsString('<USelectMenu', $hasOne);
        self::assertStringContainsString('<UAlert', $hasOne);
        self::assertStringContainsString('<UPopover', $confirm);
        self::assertStringContainsString('<UCheckbox', $hasMany);
        self::assertStringContainsString('fetchRelationPayload', $requests);
        self::assertStringNotContainsString('console.', $hasOne . $hasMany . $confirm . $requests);
    }

    private function fixture(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }
}
