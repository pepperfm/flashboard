<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Runtime;

use Pepperfm\Flashboard\Contracts\Actions\ActionContract;
use Pepperfm\Flashboard\Contracts\Actions\FormActionContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Runtime\Assemblers\ActionPayloadAssembler;

final readonly class ResourceActionPayloadAssembler
{
    public function __construct(private ActionPayloadAssembler $assembler)
    {
    }

    /**
     * @param class-string<Resource> $resourceClass
     *
     * @return array<string, mixed>
     */
    public function assemble(ActionContract $action, string $resourceClass, mixed $recordKey = null): array
    {
        $route = config('flashboard.route_name_prefix', 'flashboard.') . 'resources.' . $resourceClass::key()
            . ($recordKey === null ? '.actions.index' : '.actions.record');
        $parameters = $recordKey === null
            ? ['action' => $action->key()]
            : ['record' => $recordKey, 'action' => $action->key()];
        $payload = $this->assembler->assemble($action)->toArray();
        $payload['url'] = route($route, $parameters);
        $payload['method'] = 'post';

        if ($action instanceof FormActionContract && $action->hasForm()) {
            $payload['form_url'] = route($route . '.form', $parameters);
        }

        return $payload;
    }
}
