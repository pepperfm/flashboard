<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Actions;

use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Pepperfm\Flashboard\Contracts\Forms\FormContract;

final readonly class ActionForm
{
    private const array SUPPORTED_RENDERERS = ['input', 'textarea', 'date', 'checkbox', 'switch', 'select'];

    private const array PUBLIC_ATTRIBUTES = [
        'key', 'kind', 'label', 'type', 'renderer', 'input_type', 'required',
        'hint', 'help', 'placeholder', 'options', 'min_date', 'max_date', 'native', 'layout',
    ];

    public function __construct(private FormContract $form)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $fields = $this->fields();

        return [
            'fields' => array_values($fields),
            'schema' => $this->publicSchema((array) Arr::get($this->form->toArray(), 'schema', []), $fields),
            'defaults' => (object) $this->defaults($fields),
        ];
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function validated(array $input): array
    {
        $fields = $this->fields();
        $rules = (array) Arr::get($this->form->toArray(), 'rules', []);
        $data = array_replace($this->defaults($fields), $input);
        $errors = [];
        foreach ($input as $key => $value) {
            if (! array_key_exists($key, $fields) || (! is_scalar($value) && $value !== null)) {
                $errors[$key] = 'Invalid action parameter.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        foreach ($fields as $key => $field) {
            $fieldRules = Arr::get($rules, $key, []);
            $fieldRules = (array) $fieldRules;
            if (Arr::get($field, 'renderer') === 'select') {
                $options = (array) Arr::get($field, 'options', []);
                $values = array_is_list($options)
                    ? array_map(static fn (array $option): mixed => Arr::get($option, 'value'), $options)
                    : array_map('strval', array_keys($options));
                $fieldRules[] = Rule::in($values);
            }
            $rules[$key] = $fieldRules;
        }

        $validated = $fields
            |> array_keys(...)
            |> (static fn($x) => Arr::only($rules, $x))
            |> (static fn($x) => validator($data, $x)->validate());

        return Arr::only($validated, array_keys($fields));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function fields(): array
    {
        $fields = [];
        foreach ($this->form->fieldSchema() as $field) {
            $key = Arr::get($field, 'key');
            if (
                !is_string($key)
                || preg_match('/^[a-zA-Z_]\w*$/D', $key) !== 1
                || in_array($key, ['id', 'record', 'model', 'user', 'resource', 'payload', 'idempotency_key', '_token'], true)
            ) {
                throw new \InvalidArgumentException('Unsupported action form field key.');
            }
            $renderer = Arr::get($field, 'renderer') ?? match (Arr::get($field, 'type')) {
                'date' => 'date',
                'checkbox' => 'checkbox',
                'toggle' => 'switch',
                'select' => 'select',
                'textarea' => 'textarea',
                default => 'input',
            };
            if (
                !in_array($renderer, self::SUPPORTED_RENDERERS, true)
                || Arr::get($field, 'multiple', false)
                || !in_array(Arr::get($field, 'type'), [null, 'text', 'textarea', 'date', 'checkbox', 'toggle', 'select'], true)
                || in_array(Arr::get($field, 'input_type'), ['file', 'password'], true)
            ) {
                throw new \InvalidArgumentException('Unsupported action form field.');
            }
            if (! Arr::get($field, 'visible', true) || Arr::get($field, 'disabled', false)) {
                continue;
            }

            $publicField = Arr::only($field, self::PUBLIC_ATTRIBUTES);
            $publicField['renderer'] = $renderer;
            if ($renderer === 'select') {
                $options = (array) Arr::get($publicField, 'options', []);
                if ($options !== [] && array_is_list($options)) {
                    foreach ($options as $option) {
                        if (! is_array($option) || ! is_scalar(Arr::get($option, 'value'))) {
                            throw new \InvalidArgumentException('Action select options must declare scalar values.');
                        }
                    }
                }
            }
            $this->assertPublicValue($publicField);
            $fields[$key] = $publicField;
        }

        return $fields;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  array<string, array<string, mixed>>  $fields
     * @return list<array<string, mixed>>
     */
    private function publicSchema(array $nodes, array $fields): array
    {
        $schema = [];
        foreach ($nodes as $node) {
            $kind = Arr::get($node, 'kind', 'field');
            $key = (string) Arr::get($node, 'key');
            if ($kind === 'field') {
                if (isset($fields[$key])) {
                    $schema[] = $fields[$key];
                }

                continue;
            }

            $childKey = $kind === 'tabs' ? 'tabs' : 'schema';
            $children = $this->publicSchema((array) Arr::get($node, $childKey, []), $fields);
            if ($children === []) {
                continue;
            }

            $container = Arr::only($node, ['key', 'kind', 'label', 'description', 'icon', 'layout']);
            $container[$childKey] = $children;
            $this->assertPublicValue($container);
            $schema[] = $container;
        }

        return $schema;
    }

    /**
     * @param array<string, array<string, mixed>> $fields
     *
     * @return array<string, mixed>
     */
    private function defaults(array $fields): array
    {
        $defaults = Arr::only($this->form->defaultState(), array_keys($fields));
        foreach ($defaults as $value) {
            if (! is_scalar($value) && $value !== null) {
                throw new \InvalidArgumentException('Unsupported action form default.');
            }
        }

        return $defaults;
    }

    private function assertPublicValue(mixed $value): void
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $this->assertPublicValue($item);
            }

            return;
        }

        if (! is_scalar($value) && $value !== null) {
            throw new \InvalidArgumentException('Action form metadata must be serializable.');
        }
    }
}
