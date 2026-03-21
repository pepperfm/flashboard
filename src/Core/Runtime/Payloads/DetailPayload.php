<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Runtime\Payloads;

final readonly class DetailPayload
{
    private const string KEY_SECTIONS = 'sections';
    private const string KEY_ENTRIES = 'entries';
    private const string KEY_ACTIONS = 'actions';
    private const string KEY_HEADER_ACTIONS = 'header_actions';

    /**
     * @param array<string, mixed> $schema
     */
    public function __construct(
        private array $schema,
    ) {
    }

    public function toArray(): array
    {
        return $this->schema;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sections(): array
    {
        return (array) $this->schema[self::KEY_SECTIONS];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function entries(): array
    {
        return (array) $this->schema[self::KEY_ENTRIES];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function actions(): array
    {
        return (array) $this->schema[self::KEY_ACTIONS];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function headerActions(): array
    {
        return (array) $this->schema[self::KEY_HEADER_ACTIONS];
    }
}
