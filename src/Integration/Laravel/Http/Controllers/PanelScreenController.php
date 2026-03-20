<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Http\Controllers;

use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Navigation\NavigationBuilder;
use Pepperfm\Flashboard\Core\Panel\FlashboardManager;
use Pepperfm\Flashboard\Core\Runtime\Assemblers\ScreenPayloadAssembler;
use Pepperfm\Flashboard\Core\Runtime\Context\RuntimeContextFactory;
use Pepperfm\Flashboard\Flashboard;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;
use Pepperfm\Flashboard\UI\Layout\PanelLayoutFactory;
use Pepperfm\Flashboard\UI\Renderers\InertiaScreenRenderer;
use Pepperfm\Flashboard\UI\Renderers\JsonScreenRenderer;
use Pepperfm\Flashboard\UI\Panel\PanelShell;

final readonly class PanelScreenController
{
    public function __construct(
        private FlashboardManager $manager,
        private RuntimeContextFactory $runtimeContextFactory,
        private ScreenPayloadAssembler $screenPayloadAssembler,
        private NavigationBuilder $navigationBuilder,
        private PanelLayoutFactory $panelLayoutFactory,
        private PanelAuthenticator $authenticator,
        private ScreenAccessResolver $screenAccessResolver,
        private InertiaScreenRenderer $inertiaScreenRenderer,
        private JsonScreenRenderer $jsonScreenRenderer,
    ) {
    }

    public function __invoke(\Illuminate\Http\Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $panel = $this->manager->panel();
        $context = $this->runtimeContextFactory->make($request, $panel);
        $user = $this->authenticator->user();
        $resourceClass = $context->screen()->resourceClass();
        $resourcePage = (string) ($request->route()?->defaults['flashboard.resource_page'] ?? 'index');
        $record = is_string($resourceClass) && in_array($resourcePage, ['detail', 'edit'], true)
            ? $resourceClass::resolveRecord($request->route('record'))
            : null;

        if (
            $context->screen()->pageClass() !== null &&
            !$this->screenAccessResolver->canAccessPage($context->screen()->pageClass(), $user)
        ) {
            logger()->warning('[FIX] Denied page access.', [
                'page' => $context->screen()->pageClass(),
            ]);
            abort(403);
        }

        if (
            is_string($resourceClass) &&
            !$this->canAccessResourceScreen($resourceClass, $resourcePage, $record, $user)
        ) {
            logger()->warning('[FIX] Denied resource screen access.', [
                'page' => $resourcePage,
                'resource' => $resourceClass,
                'record' => $record?->getKey(),
            ]);
            abort(403);
        }

        $payload = $this->screenPayloadAssembler->assemble($context)->toArray();
        $navigation = $this->navigationBuilder->build($panel);
        $layout = $this->panelLayoutFactory->make(
            context: $context,
            navigation: $navigation,
            payload: $payload,
            user: $user,
        )->toArray();
        $shell = PanelShell::placeholder($panel);

        if ($request->expectsJson()) {
            return $this->jsonScreenRenderer->render($request, $panel, $shell, $layout, $payload, $user, Flashboard::VERSION);
        }

        return $this->inertiaScreenRenderer->render($request, $panel, $shell, $layout, $payload, $user, Flashboard::VERSION);
    }

    private function canAccessResourceScreen(
        string $resourceClass,
        string $resourcePage,
        ?\Illuminate\Database\Eloquent\Model $record,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
    ): bool {
        return match ($resourcePage) {
            'create' => $this->screenAccessResolver->canCreateRecord($resourceClass, $user),
            'edit' => $this->screenAccessResolver->canEditRecord($resourceClass, $user, $record),
            'detail' => $this->screenAccessResolver->canViewRecord($resourceClass, $user, $record),
            default => $this->screenAccessResolver->canAccessResource($resourceClass, $user),
        };
    }
}
