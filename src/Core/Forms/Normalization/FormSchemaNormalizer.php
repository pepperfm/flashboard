<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Forms\Normalization;

use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Contracts\Forms\FieldRenderer;
use Pepperfm\Flashboard\Contracts\Forms\FormLayoutAlign;
use Pepperfm\Flashboard\Contracts\Forms\FormLayoutAttribute;
use Pepperfm\Flashboard\Contracts\Forms\FormLayoutDirection;
use Pepperfm\Flashboard\Contracts\Forms\FormLayoutJustify;
use Pepperfm\Flashboard\Contracts\Forms\FormLayoutMode;
use Pepperfm\Flashboard\Contracts\Forms\FormSchemaNodeKind;
use Pepperfm\Flashboard\Core\Forms\Fields\Field;
use Pepperfm\Flashboard\Support\Schema\SchemaNodeNormalizer;

final class FormSchemaNormalizer
{
    private const string KEY_DEFAULTS = 'defaults';
    private const string KEY_FIELDS = 'fields';
    private const string KEY_KIND = 'kind';
    private const string KEY_LAYOUT = 'layout';
    private const string KEY_RULES = 'rules';
    private const string KEY_SCHEMA = 'schema';
    private const string KEY_SECTIONS = 'sections';
    private const string KEY_TABS = 'tabs';

    /**
     * @param array<string, mixed> $definition
     *
     * @return array<string, mixed>
     */
    public function normalize(array $definition): array
    {
        $schema = $this->normalizeSchemaTree($definition);
        $flattenedFields = $this->flattenFieldNodes($schema);
        $deduplicatedFields = $this->deduplicateKeyedNodes($flattenedFields);
        $explicitRules = (array) Arr::get($definition, self::KEY_RULES, []);
        $layout = $this->normalizeContainerLayout($definition);

        return [
            self::KEY_LAYOUT => $layout,
            self::KEY_SCHEMA => $schema,
            self::KEY_SECTIONS => $this->extractRootSections($schema),
            self::KEY_TABS => $this->extractRootTabs($schema),
            self::KEY_FIELDS => $deduplicatedFields,
            self::KEY_RULES => $this->mergeRules(
                $this->inferRulesFromFields($deduplicatedFields),
                $explicitRules,
            ),
            self::KEY_DEFAULTS => (array) Arr::get($definition, self::KEY_DEFAULTS, []),
            'has_mutate_data_using' => (bool) Arr::get($definition, 'has_mutate_data_using', false),
            'has_after_save' => (bool) Arr::get($definition, 'has_after_save', false),
        ];
    }

    /**
     * @param list<array<string, mixed>> $nodes
     *
     * @return list<array<string, mixed>>
     */
    private function deduplicateKeyedNodes(array $nodes): array
    {
        $deduplicated = [];

        foreach ($nodes as $node) {
            $key = (string) Arr::get($node, 'key', '');

            if ($key === '') {
                $deduplicated[] = $node;
                continue;
            }

            $deduplicated[$key] = $node;
        }

        return array_values($deduplicated);
    }

    /**
     * @param list<array<string, mixed>> $fields
     *
     * @return array<string, list<mixed>>
     */
    private function inferRulesFromFields(array $fields): array
    {
        $rules = [];

        foreach ($fields as $field) {
            $key = trim((string) Arr::get($field, 'key', ''));

            if ($key === '') {
                continue;
            }

            $fieldRules = [];
            $isRequired = (bool) Arr::get($field, Field::ATTRIBUTE_REQUIRED, false);
            $type = (string) Arr::get($field, Field::ATTRIBUTE_TYPE, '');
            $inputType = (string) Arr::get($field, Field::ATTRIBUTE_INPUT_TYPE, '');
            $renderer = $this->resolveFieldRenderer($field);

            $fieldRules[] = $isRequired ? 'required' : 'nullable';

            if ($renderer === FieldRenderer::Input || $renderer === FieldRenderer::Textarea || $inputType === 'email') {
                $fieldRules[] = 'string';
            }

            if ($inputType === 'email') {
                $fieldRules[] = 'email';
            }

            if ($renderer === FieldRenderer::Switch || $type === Field::TYPE_TOGGLE) {
                $fieldRules[] = 'boolean';
            }

            $rules[$key] = array_values(array_unique($fieldRules));
        }

        return $rules;
    }

    /**
     * @param array<string, mixed> $inferredRules
     * @param array<string, mixed> $explicitRules
     *
     * @return array<string, mixed>
     */
    private function mergeRules(array $inferredRules, array $explicitRules): array
    {
        $merged = $inferredRules;

        foreach ($explicitRules as $field => $rules) {
            $inferred = $merged[$field] ?? [];

            if (!is_array($rules)) {
                $rules = [$rules];
            }

            if (!is_array($inferred)) {
                $inferred = [$inferred];
            }

            $merged[$field] = array_values(array_unique(array_merge($inferred, $rules), SORT_REGULAR));
        }

        return $merged;
    }

    /**
     * @param array<string, mixed> $field
     *
     * @return array<string, mixed>
     */
    private function normalizeField(array $field): array
    {
        $field[self::KEY_KIND] = FormSchemaNodeKind::Field->value;
        $field[Field::ATTRIBUTE_RENDERER] = $this->resolveFieldRenderer($field)->value;
        $field[self::KEY_LAYOUT] = $this->normalizeFieldLayout($field);

        return $this->stripFlatLayoutKeys($field);
    }

    /**
     * @param array<string, mixed> $definition
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeSchemaTree(array $definition): array
    {
        return $this->normalizeSchemaNodes($this->composeRootSchema($definition));
    }

    /**
     * @param list<array<string, mixed>|\Pepperfm\Flashboard\Contracts\Schema\KeyedSchemaNodeContract> $nodes
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeSchemaNodes(array $nodes, bool $insideTabs = false): array
    {
        return array_values(array_map(
            fn (array|\Pepperfm\Flashboard\Contracts\Schema\KeyedSchemaNodeContract $node): array => $this->normalizeSchemaNode($node, $insideTabs),
            $nodes,
        ));
    }

    /**
     * @param array<string, mixed>|\Pepperfm\Flashboard\Contracts\Schema\KeyedSchemaNodeContract $node
     *
     * @return array<string, mixed>
     */
    private function normalizeSchemaNode(array|\Pepperfm\Flashboard\Contracts\Schema\KeyedSchemaNodeContract $node, bool $insideTabs): array
    {
        $normalized = SchemaNodeNormalizer::normalizeKeyedNode($node);
        $kind = $this->resolveSchemaNodeKind($normalized, $insideTabs);

        return match ($kind) {
            FormSchemaNodeKind::Field => $this->normalizeField($normalized),
            FormSchemaNodeKind::Section => $this->normalizeSectionNode($normalized),
            FormSchemaNodeKind::Tabs => $this->normalizeTabsNode($normalized),
            FormSchemaNodeKind::Tab => $this->normalizeTabNode($normalized),
        };
    }

    /**
     * @param array<string, mixed> $node
     *
     * @return array<string, mixed>
     */
    private function normalizeSectionNode(array $node): array
    {
        $node[self::KEY_KIND] = FormSchemaNodeKind::Section->value;
        $node[self::KEY_LAYOUT] = $this->normalizeContainerLayout($node);
        $node[self::KEY_SCHEMA] = $this->normalizeSchemaNodes(
            is_array(Arr::get($node, self::KEY_SCHEMA, [])) ? (array) Arr::get($node, self::KEY_SCHEMA, []) : [],
        );

        return $this->stripFlatLayoutKeys($node);
    }

    /**
     * @param array<string, mixed> $node
     *
     * @return array<string, mixed>
     */
    private function normalizeTabNode(array $node): array
    {
        $node[self::KEY_KIND] = FormSchemaNodeKind::Tab->value;
        $node[self::KEY_LAYOUT] = $this->normalizeContainerLayout($node);
        $node[self::KEY_SCHEMA] = $this->normalizeSchemaNodes(
            is_array(Arr::get($node, self::KEY_SCHEMA, [])) ? (array) Arr::get($node, self::KEY_SCHEMA, []) : [],
        );

        return $this->stripFlatLayoutKeys($node);
    }

    /**
     * @param array<string, mixed> $node
     *
     * @return array<string, mixed>
     */
    private function normalizeTabsNode(array $node): array
    {
        $node[self::KEY_KIND] = FormSchemaNodeKind::Tabs->value;
        $tabs = Arr::get($node, self::KEY_TABS, Arr::get($node, self::KEY_SCHEMA, []));
        $node[self::KEY_TABS] = $this->normalizeSchemaNodes(
            is_array($tabs) ? $tabs : [],
            insideTabs: true,
        );
        unset($node[self::KEY_SCHEMA]);

        return $node;
    }

    /**
     * @param list<array<string, mixed>> $schema
     *
     * @return list<array<string, mixed>>
     */
    private function flattenFieldNodes(array $schema): array
    {
        $fields = [];

        foreach ($schema as $node) {
            $kind = FormSchemaNodeKind::from((string) Arr::get($node, self::KEY_KIND, FormSchemaNodeKind::Field->value));

            if ($kind === FormSchemaNodeKind::Field) {
                $fields[] = $node;
                continue;
            }

            if ($kind === FormSchemaNodeKind::Tabs) {
                $fields = array_merge(
                    $fields,
                    $this->flattenFieldNodes((array) Arr::get($node, self::KEY_TABS, [])),
                );

                continue;
            }

            $fields = array_merge(
                $fields,
                $this->flattenFieldNodes((array) Arr::get($node, self::KEY_SCHEMA, [])),
            );
        }

        return $fields;
    }

    /**
     * @param list<array<string, mixed>> $schema
     *
     * @return list<array<string, mixed>>
     */
    private function extractRootSections(array $schema): array
    {
        return array_values(array_filter(
            $schema,
            fn (array $node): bool => Arr::get($node, self::KEY_KIND) === FormSchemaNodeKind::Section->value,
        ));
    }

    /**
     * @param list<array<string, mixed>> $schema
     *
     * @return list<array<string, mixed>>
     */
    private function extractRootTabs(array $schema): array
    {
        $tabs = [];

        foreach ($schema as $node) {
            if (Arr::get($node, self::KEY_KIND) !== FormSchemaNodeKind::Tabs->value) {
                continue;
            }

            foreach ((array) Arr::get($node, self::KEY_TABS, []) as $tab) {
                if (is_array($tab)) {
                    $tabs[] = $tab;
                }
            }
        }

        return $tabs;
    }

    /**
     * @param array<string, mixed> $definition
     *
     * @return list<array<string, mixed>|\Pepperfm\Flashboard\Contracts\Schema\KeyedSchemaNodeContract>
     */
    private function composeRootSchema(array $definition): array
    {
        $schema = (array) Arr::get($definition, self::KEY_SCHEMA, []);
        $fields = (array) Arr::get($definition, self::KEY_FIELDS, []);
        $sections = (array) Arr::get($definition, self::KEY_SECTIONS, []);
        $tabs = (array) Arr::get($definition, self::KEY_TABS, []);

        $nodes = [...$schema, ...$fields, ...$sections];

        if ($tabs !== []) {
            $nodes[] = [
                self::KEY_KIND => FormSchemaNodeKind::Tabs->value,
                'key' => 'tabs',
                self::KEY_TABS => $tabs,
            ];
        }

        return $nodes;
    }

    /**
     * @param array<string, mixed> $node
     */
    private function resolveSchemaNodeKind(array $node, bool $insideTabs): FormSchemaNodeKind
    {
        if (Arr::has($node, self::KEY_KIND)) {
            $kind = FormSchemaNodeKind::tryFrom((string) Arr::get($node, self::KEY_KIND, ''));

            if ($kind !== null) {
                return $kind;
            }

            throw new \InvalidArgumentException(sprintf(
                'Unknown form schema node kind [%s].',
                (string) Arr::get($node, self::KEY_KIND, ''),
            ));
        }

        if (Arr::has($node, self::KEY_TABS)) {
            return FormSchemaNodeKind::Tabs;
        }

        if (Arr::has($node, self::KEY_SCHEMA)) {
            return $insideTabs ? FormSchemaNodeKind::Tab : FormSchemaNodeKind::Section;
        }

        return FormSchemaNodeKind::Field;
    }

    /**
     * @param array<string, mixed> $field
     */
    private function resolveFieldRenderer(array $field): FieldRenderer
    {
        if (Arr::has($field, Field::ATTRIBUTE_RENDERER)) {
            $renderer = FieldRenderer::tryFrom((string) Arr::get($field, Field::ATTRIBUTE_RENDERER, ''));

            if ($renderer !== null) {
                return $renderer;
            }

            throw new \InvalidArgumentException(sprintf(
                'Unknown form field renderer [%s] for field [%s].',
                (string) Arr::get($field, Field::ATTRIBUTE_RENDERER, ''),
                trim((string) Arr::get($field, 'key', '')) ?: 'unknown',
            ));
        }

        return match ((string) Arr::get($field, Field::ATTRIBUTE_TYPE, '')) {
            Field::TYPE_SELECT => FieldRenderer::Select,
            Field::TYPE_TEXTAREA => FieldRenderer::Textarea,
            Field::TYPE_TOGGLE => FieldRenderer::Switch,
            default => FieldRenderer::Input,
        };
    }

    /**
     * @param array<string, mixed> $definition
     *
     * @return array<string, mixed>
     */
    private function normalizeContainerLayout(array $definition): array
    {
        $hasGridKeys = Arr::has($definition, FormLayoutAttribute::KEY_COLUMNS);
        $hasFlexKeys = Arr::hasAny($definition, [
            FormLayoutAttribute::KEY_DIRECTION,
            FormLayoutAttribute::KEY_JUSTIFY,
            FormLayoutAttribute::KEY_ALIGN,
            FormLayoutAttribute::KEY_WRAP,
        ]);
        $hasGap = Arr::has($definition, FormLayoutAttribute::KEY_GAP);
        $rawMode = Arr::get($definition, FormLayoutAttribute::KEY_LAYOUT);

        if ($rawMode === null && !$hasGridKeys && !$hasFlexKeys && !$hasGap) {
            return [];
        }

        $mode = $this->resolveContainerLayoutMode($definition, $hasGridKeys, $hasFlexKeys);

        if ($mode === FormLayoutMode::Grid && $hasFlexKeys) {
            throw new \InvalidArgumentException('Grid form layouts cannot define flex direction, justify, align, or wrap.');
        }

        if ($mode === FormLayoutMode::Flex && $hasGridKeys) {
            throw new \InvalidArgumentException('Flex form layouts cannot define grid columns.');
        }

        if ($mode === FormLayoutMode::Stack && ($hasGridKeys || $hasFlexKeys)) {
            throw new \InvalidArgumentException('Stack form layouts cannot define grid or flex-specific settings.');
        }

        $layout = [
            'mode' => $mode->value,
        ];

        if ($hasGap) {
            $layout[FormLayoutAttribute::KEY_GAP] = $this->normalizeResponsiveInteger(
                Arr::get($definition, FormLayoutAttribute::KEY_GAP),
                FormLayoutAttribute::KEY_GAP,
                allowZero: true,
            );
        }

        if ($mode === FormLayoutMode::Grid) {
            $layout[FormLayoutAttribute::KEY_COLUMNS] = $this->normalizeColumns(
                Arr::get($definition, FormLayoutAttribute::KEY_COLUMNS, 1),
            );
        }

        if ($mode === FormLayoutMode::Flex) {
            if (Arr::has($definition, FormLayoutAttribute::KEY_DIRECTION)) {
                $layout[FormLayoutAttribute::KEY_DIRECTION] = $this->normalizeDirection(
                    (string) Arr::get($definition, FormLayoutAttribute::KEY_DIRECTION, ''),
                );
            }

            if (Arr::has($definition, FormLayoutAttribute::KEY_JUSTIFY)) {
                $layout[FormLayoutAttribute::KEY_JUSTIFY] = $this->normalizeJustify(
                    (string) Arr::get($definition, FormLayoutAttribute::KEY_JUSTIFY, ''),
                );
            }

            if (Arr::has($definition, FormLayoutAttribute::KEY_ALIGN)) {
                $layout[FormLayoutAttribute::KEY_ALIGN] = $this->normalizeAlign(
                    (string) Arr::get($definition, FormLayoutAttribute::KEY_ALIGN, ''),
                );
            }

            if (Arr::has($definition, FormLayoutAttribute::KEY_WRAP)) {
                $layout[FormLayoutAttribute::KEY_WRAP] = (bool) Arr::get(
                    $definition,
                    FormLayoutAttribute::KEY_WRAP,
                    false,
                );
            }
        }

        return $layout;
    }

    /**
     * @param array<string, mixed> $field
     *
     * @return array<string, mixed>
     */
    private function normalizeFieldLayout(array $field): array
    {
        if (!Arr::has($field, FormLayoutAttribute::KEY_COLUMN_SPAN)) {
            return [];
        }

        return [
            FormLayoutAttribute::KEY_COLUMN_SPAN => $this->normalizeColumnSpan(
                Arr::get($field, FormLayoutAttribute::KEY_COLUMN_SPAN),
            ),
        ];
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function resolveContainerLayoutMode(array $definition, bool $hasGridKeys, bool $hasFlexKeys): FormLayoutMode
    {
        if (Arr::has($definition, FormLayoutAttribute::KEY_LAYOUT)) {
            $mode = FormLayoutMode::tryFrom((string) Arr::get($definition, FormLayoutAttribute::KEY_LAYOUT, ''));

            if ($mode !== null) {
                return $mode;
            }

            throw new \InvalidArgumentException(sprintf(
                'Unknown form layout mode [%s].',
                (string) Arr::get($definition, FormLayoutAttribute::KEY_LAYOUT, ''),
            ));
        }

        if ($hasGridKeys && $hasFlexKeys) {
            throw new \InvalidArgumentException('Form layouts cannot mix grid columns with flex-specific settings.');
        }

        if ($hasGridKeys) {
            return FormLayoutMode::Grid;
        }

        if ($hasFlexKeys) {
            return FormLayoutMode::Flex;
        }

        return FormLayoutMode::Stack;
    }

    /**
     * @return array<string, int>
     */
    private function normalizeColumns(mixed $columns): array
    {
        if (is_int($columns)) {
            if ($columns < 1) {
                throw new \InvalidArgumentException('Form layout columns must be greater than or equal to 1.');
            }

            if ($columns === 1) {
                return [
                    FormLayoutAttribute::BREAKPOINT_DEFAULT => 1,
                ];
            }

            return [
                FormLayoutAttribute::BREAKPOINT_DEFAULT => 1,
                FormLayoutAttribute::BREAKPOINT_MD => $columns,
            ];
        }

        return $this->normalizeResponsiveInteger($columns, FormLayoutAttribute::KEY_COLUMNS);
    }

    /**
     * @return array<string, int|string>|int|string
     */
    private function normalizeColumnSpan(mixed $columnSpan): array|int|string
    {
        if (is_int($columnSpan)) {
            if ($columnSpan < 1) {
                throw new \InvalidArgumentException('Form field column spans must be greater than or equal to 1.');
            }

            return $columnSpan;
        }

        if (is_string($columnSpan)) {
            if ($columnSpan === FormLayoutAttribute::VALUE_FULL) {
                return FormLayoutAttribute::VALUE_FULL;
            }

            throw new \InvalidArgumentException(sprintf(
                'Unknown form field column span [%s].',
                $columnSpan,
            ));
        }

        if (!is_array($columnSpan)) {
            throw new \InvalidArgumentException('Form field column spans must be an integer, "full", or a responsive map.');
        }

        $normalized = [];

        foreach ($columnSpan as $breakpoint => $value) {
            $normalizedBreakpoint = $this->normalizeBreakpointKey($breakpoint);

            if (is_int($value)) {
                if ($value < 1) {
                    throw new \InvalidArgumentException('Form field column spans must be greater than or equal to 1.');
                }

                $normalized[$normalizedBreakpoint] = $value;
                continue;
            }

            if ($value === FormLayoutAttribute::VALUE_FULL) {
                $normalized[$normalizedBreakpoint] = FormLayoutAttribute::VALUE_FULL;
                continue;
            }

            throw new \InvalidArgumentException(sprintf(
                'Unknown form field column span [%s] for breakpoint [%s].',
                (string) $value,
                (string) $breakpoint,
            ));
        }

        return $normalized;
    }

    /**
     * @return array<string, int>
     */
    private function normalizeResponsiveInteger(mixed $value, string $attribute, bool $allowZero = false): array
    {
        if (is_int($value)) {
            if ((!$allowZero && $value < 1) || ($allowZero && $value < 0)) {
                throw new \InvalidArgumentException(sprintf(
                    'Form layout [%s] values must be %s.',
                    $attribute,
                    $allowZero ? 'greater than or equal to 0' : 'greater than or equal to 1',
                ));
            }

            return [
                FormLayoutAttribute::BREAKPOINT_DEFAULT => $value,
            ];
        }

        if (!is_array($value)) {
            throw new \InvalidArgumentException(sprintf(
                'Form layout [%s] must be an integer or a responsive map.',
                $attribute,
            ));
        }

        $normalized = [];

        foreach ($value as $breakpoint => $breakpointValue) {
            if (!is_int($breakpointValue)) {
                throw new \InvalidArgumentException(sprintf(
                    'Form layout [%s] values must be integers.',
                    $attribute,
                ));
            }

            if ((!$allowZero && $breakpointValue < 1) || ($allowZero && $breakpointValue < 0)) {
                throw new \InvalidArgumentException(sprintf(
                    'Form layout [%s] values must be %s.',
                    $attribute,
                    $allowZero ? 'greater than or equal to 0' : 'greater than or equal to 1',
                ));
            }

            $normalized[$this->normalizeBreakpointKey($breakpoint)] = $breakpointValue;
        }

        return $normalized;
    }

    private function normalizeDirection(string $direction): string
    {
        $normalizedDirection = FormLayoutDirection::tryFrom($direction);

        if ($normalizedDirection !== null) {
            return $normalizedDirection->value;
        }

        throw new \InvalidArgumentException(sprintf(
            'Unknown form layout direction [%s].',
            $direction,
        ));
    }

    private function normalizeJustify(string $justify): string
    {
        $normalizedJustify = FormLayoutJustify::tryFrom($justify);

        if ($normalizedJustify !== null) {
            return $normalizedJustify->value;
        }

        throw new \InvalidArgumentException(sprintf(
            'Unknown form layout justify [%s].',
            $justify,
        ));
    }

    private function normalizeAlign(string $align): string
    {
        $normalizedAlign = FormLayoutAlign::tryFrom($align);

        if ($normalizedAlign !== null) {
            return $normalizedAlign->value;
        }

        throw new \InvalidArgumentException(sprintf(
            'Unknown form layout align [%s].',
            $align,
        ));
    }

    private function normalizeBreakpointKey(string|int $breakpoint): string
    {
        $normalizedBreakpoint = (string) $breakpoint;

        if (in_array($normalizedBreakpoint, FormLayoutAttribute::BREAKPOINTS, true)) {
            return $normalizedBreakpoint;
        }

        throw new \InvalidArgumentException(sprintf(
            'Unknown form layout breakpoint [%s].',
            $normalizedBreakpoint,
        ));
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function stripFlatLayoutKeys(array $payload): array
    {
        unset(
            $payload[FormLayoutAttribute::KEY_COLUMNS],
            $payload[FormLayoutAttribute::KEY_GAP],
            $payload[FormLayoutAttribute::KEY_DIRECTION],
            $payload[FormLayoutAttribute::KEY_JUSTIFY],
            $payload[FormLayoutAttribute::KEY_ALIGN],
            $payload[FormLayoutAttribute::KEY_WRAP],
            $payload[FormLayoutAttribute::KEY_COLUMN_SPAN],
        );

        return $payload;
    }
}
