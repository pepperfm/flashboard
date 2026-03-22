<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Runtime\Payloads;

final readonly class FormPayload
{
    private const string KEY_SCHEMA = 'schema';
    private const string KEY_SECTIONS = 'sections';
    private const string KEY_TABS = 'tabs';
    private const string KEY_FIELDS = 'fields';
    private const string KEY_RULES = 'rules';
    private const string KEY_DEFAULTS = 'defaults';

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
    public function schema(): array
    {
        return (array) $this->schema[self::KEY_SCHEMA];
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
    public function tabs(): array
    {
        return (array) $this->schema[self::KEY_TABS];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fields(): array
    {
        return (array) $this->schema[self::KEY_FIELDS];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return (array) $this->schema[self::KEY_RULES];
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return (array) $this->schema[self::KEY_DEFAULTS];
    }
}
