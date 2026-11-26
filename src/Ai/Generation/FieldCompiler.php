<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Ai\Generation;

final class FieldCompiler
{
    /**
     * @param list<mixed> $fields
     * @param array<string, string> $available
     * @param array<string, true> $foreignKeys
     *
     * @return array{
     *     table: list<string>,
     *     form: list<string>,
     *     detail: list<string>,
     *     filters: list<string>,
     *     rules: list<string>
     * }
     */
    public function compile(
        \Illuminate\Database\Eloquent\Model $model,
        array $fields,
        array $available,
        array $foreignKeys,
        bool $readOnly,
    ): array {
        $hidden = $model->getHidden();
        $primary = $model->getKeyName();

        $table = [];
        $form = [];
        $detail = [];
        $filters = [];
        $rules = [];
        $seen = [];

        foreach ($fields as $item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException('Each field must be an object.');
            }

            $name = $item['name'] ?? null;
            $type = $item['type'] ?? 'text';
            if (
                !is_string($name)
                || !isset($available[$name])
                || isset($seen[$name])
                || !is_string($type)
                || !preg_match('/^[a-zA-Z_]\w*$/D', $name)
                || !in_array($type, ['text', 'textarea', 'number', 'date', 'select', 'checkbox', 'toggle', 'enum'], true)
            ) {
                throw new \InvalidArgumentException('Field names must be unique, valid database columns with supported types.');
            }

            $seen[$name] = true;
            if (
                in_array($name, $hidden, true)
                || preg_match('/(?:password|secret|token|api_key|private_key|remember_token)/i', $name)
            ) {
                throw new \InvalidArgumentException("Sensitive field [$name] cannot be generated automatically.");
            }

            $label = $item['label'] ?? str($name)->headline()->value();
            if (!is_string($label) || strlen($label) > 100) {
                throw new \InvalidArgumentException('Field labels must be strings up to 100 bytes.');
            }

            $labelPhp = var_export($label, true);
            $namePhp = var_export($name, true);
            $showTable = $item['table'] ?? true;
            $showForm = $item['form'] ?? (!$readOnly && $name !== $primary && $model->isFillable($name) && !in_array($name, [$model->getCreatedAtColumn(), $model->getUpdatedAtColumn()], true));
            $showDetail = $item['detail'] ?? true;
            $required = $item['required'] ?? false;
            $searchable = $item['searchable'] ?? false;
            $sortable = $item['sortable'] ?? false;
            $filter = $item['filter'] ?? false;

            foreach (compact('showTable', 'showForm', 'showDetail', 'required', 'searchable', 'sortable', 'filter') as $flag) {
                if (!is_bool($flag)) {
                    throw new \InvalidArgumentException('Field visibility and behavior flags must be booleans.');
                }
            }

            if ($showForm && ($readOnly || $name === $primary || !$model->isFillable($name))) {
                throw new \InvalidArgumentException("Field [$name] is not editable on this resource.");
            }

            $enum = $type === 'enum' ? new EnumOptions()->inspect($model, $name) : null;
            if ($type === 'enum') {
                if ($enum === null) {
                    throw new \InvalidArgumentException("Field [$name] has no BackedEnum cast.");
                }
                $type = 'select';
            }

            $options = [];
            if ($type === 'select') {
                $options = $item['options'] ?? $enum['options'] ?? null;
                if (!is_array($options) || ($enum === null && array_is_list($options)) || count($options) === 0 || count($options) > 50) {
                    throw new \InvalidArgumentException("Select field [$name] needs an options map of up to 50 entries.");
                }
                if ($enum !== null && array_diff_key($options, $enum['options']) !== []) {
                    throw new \InvalidArgumentException("Unknown enum option for [$name].");
                }
                foreach ($options as $optionLabel) {
                    if (!is_string($optionLabel) || strlen($optionLabel) > 100) {
                        throw new \InvalidArgumentException('Select options require scalar keys and string labels.');
                    }
                }
            }

            $optionsPhp = '[' . implode(', ', array_map(
                static fn (string|int $key, string $value): string => var_export($key, true) . ' => ' . var_export($value, true),
                array_keys($options),
                array_values($options),
            )) . ']';

            $enumItemsPhp = $enum === null ? $optionsPhp : '[' . implode(', ', array_map(
                static fn(int|string $key, string $label): string => "['value' => " . var_export($enum['backing'] === 'int' ? (int) $key : (string) $key, true) . ", 'label' => " . var_export($label, true) . ']',
                array_keys($options),
                array_values($options),
            )).']';

            if ($showTable) {
                $columnType = $type === 'select' ? 'BadgeColumn' : 'TextColumn';
                $line = "                $columnType::make($namePhp, $labelPhp)";
                if ($searchable && !in_array($type, ['checkbox', 'toggle'], true)) {
                    $line .= '->searchable()';
                }
                if ($sortable) {
                    $line .= '->sortable()';
                }
                $table[] = $line . ',';
            }

            if ($showForm && !isset($foreignKeys[$name])) {
                $fieldType = match ($type) {
                    'textarea' => 'Textarea',
                    'number' => 'NumberInput',
                    'date' => 'DateInput',
                    'select' => 'Select',
                    'checkbox' => 'Checkbox',
                    'toggle' => 'Toggle',
                    default => 'TextInput',
                };
                $line = "                $fieldType::make($namePhp, $labelPhp)";
                if ($type === 'select') {
                    $line .= "->options($enumItemsPhp)";
                }
                if ($required) {
                    $line .= '->required()';
                }
                $form[] = $line . ',';
                $validation = $required ? 'required' : 'nullable';
                $validationType = match ($type) {
                    'date' => 'date_format:Y-m-d',
                    'number' => 'numeric',
                    'checkbox', 'toggle' => 'boolean',
                    default => 'string',
                };
                if ($enum !== null) {
                    $validationType = $enum['backing'] === 'int' ? 'integer' : 'string';
                    $rules[] = "                $namePhp => ['$validation', '$validationType', \\Illuminate\\Validation\\Rule::in(array_keys($optionsPhp))],";
                } else {
                    $rules[] = "                $namePhp => ['$validation', '$validationType'],";
                }
            }

            if ($showDetail) {
                $detail[] = "                TextEntry::make($namePhp, $labelPhp),";
            }

            $filterType = $item['filter_type'] ?? ($filter ? ($type === 'select' ? 'select' : 'input') : null);
            if ($filterType !== null && !in_array($filterType, ['input', 'select', 'date'], true)) {
                throw new \InvalidArgumentException('Unsupported filter type.');
            }

            if ($filterType !== null && $showTable) {
                if ($filterType === 'select' && $options === []) {
                    throw new \InvalidArgumentException("Select filter [$name] needs a select or enum field.");
                }
                if ($filterType === 'date' && !ColumnType::isDate($available[$name])) {
                    throw new \InvalidArgumentException("Date filter [$name] requires a date or timestamp column.");
                }

                $filters[] = match ($filterType) {
                    'select' => "                SelectFilter::make($namePhp, $labelPhp)->options($enumItemsPhp),",
                    'date' => "                \\Pepperfm\\Flashboard\\Core\\Tables\\Filters\\DateFilter::make($namePhp, $labelPhp),",
                    default => "                InputFilter::make($namePhp, $labelPhp),",
                };
            }
        }

        return compact('table', 'form', 'detail', 'filters', 'rules');
    }
}
