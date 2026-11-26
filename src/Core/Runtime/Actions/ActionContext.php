<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Runtime\Actions;

final readonly class ActionContext
{
    /**
     * @param class-string<\Pepperfm\Flashboard\Contracts\Resources\Resource> $resource
     */
    public function __construct(
        private string $resource,
        private ?\Illuminate\Contracts\Auth\Authenticatable $user,
        private ?\Illuminate\Database\Eloquent\Model $record,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'record' => $this->record?->getKey(),
            'model' => $this->record,
            'user' => $this->user,
            'resource' => $this->resource,
        ];
    }
}
