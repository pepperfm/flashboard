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

    /** @var array<string, mixed> */
    private array $schema;

    /**
     * @param array<string, mixed> $schema
     */
    public function __construct(array $schema)
    {
        $this->schema = self::publicMetadata($schema);
    }

    /**
     * Form definitions are server-side; never serialize Laravel validation rules.
     * Keep an empty root rules key for compatibility with existing payload consumers.
     *
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    public static function publicMetadata(array $schema): array
    {
        $schema[self::KEY_RULES] = [];

        foreach ([self::KEY_SCHEMA, self::KEY_SECTIONS, self::KEY_TABS, self::KEY_FIELDS] as $key) {
            if (isset($schema[$key]) && is_array($schema[$key])) {
                $schema[$key] = self::publicNodes($schema[$key]);
            }
        }

        return $schema;
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @return list<array<string, mixed>>
     */
    private static function publicNodes(array $nodes): array
    {
        foreach ($nodes as &$node) {
            unset($node[self::KEY_RULES]);

            foreach ([self::KEY_SCHEMA, self::KEY_SECTIONS, self::KEY_TABS, self::KEY_FIELDS] as $key) {
                if (isset($node[$key]) && is_array($node[$key])) {
                    $node[$key] = self::publicNodes($node[$key]);
                }
            }
        }

        return $nodes;
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
     * @deprecated Validation rules are server-only. This compatibility accessor returns an empty array.
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
