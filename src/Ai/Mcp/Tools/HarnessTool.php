<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Ai\Mcp\Tools;

use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Response;
use Illuminate\Contracts\JsonSchema\JsonSchema;

abstract class HarnessTool extends Tool
{
    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    protected function specificationSchema(JsonSchema $schema): array
    {
        return [
            'resource' => $schema->string()->description('Simple resource name, e.g. Orders. Generated under app/Flashboard/Ai.')->required(),
            'model' => $schema->string()->description('Fully qualified Eloquent model under App\\Models.')->required(),
            'fields' => $schema->array()->items($schema->object([
                'name' => $schema->string()->description('Existing DB column.')->required(),
                'type' => $schema->string()->description('text, textarea, number, date, select, checkbox, toggle or enum; defaults to text.'),
                'label' => $schema->string(),
                'table' => $schema->boolean(),
                'form' => $schema->boolean(),
                'detail' => $schema->boolean(),
                'searchable' => $schema->boolean(),
                'sortable' => $schema->boolean(),
                'filter' => $schema->boolean(),
                'filter_type' => $schema->string()->description('Explicit input, select, date or date_range filter; overrides filter.'),
                'required' => $schema->boolean(),
                'options' => $schema->object()->description('Map of option value => label; required for select, optional for enum.'),
            ]))->min(1)->max(30)->required(),
            'relations' => $schema->array()->items($schema->object([
                'name' => $schema->string()->description('Actual Eloquent relationship method name.')->required(),
                'type' => $schema->string()->description('belongs_to, belongs_to_many, has_one, has_many.')->required(),
                'label' => $schema->string(),
                'resource' => $schema->string()->description('Optional registered related Resource class; required if ambiguous.'),
                'title_attribute' => $schema->string()->description('Existing visible related table column (name/title preferred).'),
                'search_columns' => $schema->array()->items($schema->string()),
                'show_on_edit' => $schema->boolean()->description('HasOne/HasMany manager only.'),
                'read_only' => $schema->boolean()->description('HasOne/HasMany manager; defaults true.'),
            ]))->description('Existing Eloquent relationships; requires a registered related Resource.'),
            'metrics' => $schema->array()->items($schema->object([
                'key' => $schema->string()->required(),
                'kind' => $schema->string()->description('count, trend, breakdown, sum or avg')->required(),
                'column' => $schema->string(),
                'label' => $schema->string(),
                'chart' => $schema->string(),
                'span' => $schema->integer(),
            ]))->max(10),
            'read_only' => $schema->boolean()->description('Disable standard record mutations; omit editable form and relation fields. Defaults false for existing clients.'),
            'allow_delete' => $schema->boolean()->description('Enable delete action; defaults false.'),
        ];
    }

    protected function respond(callable $callback): Response
    {
        try {
            return $this->jsonResponse($callback());
        } catch (\Throwable $exception) {
            return Response::error($exception->getMessage());
        }
    }

    protected function respondInspection(callable $callback): Response
    {
        try {
            return $this->jsonResponse($callback());
        } catch (\Throwable) {
            return Response::error('Inspection failed. Check the registered resource identifier, declarations and database schema.');
        }
    }

    private function jsonResponse(mixed $content): Response
    {
        return Response::text(json_encode(
            $content,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }
}
