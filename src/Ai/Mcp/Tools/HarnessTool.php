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
                'type' => $schema->string()->description('text, textarea, number, date, select, checkbox or toggle; defaults to text.'),
                'label' => $schema->string(),
                'table' => $schema->boolean(),
                'form' => $schema->boolean(),
                'detail' => $schema->boolean(),
                'searchable' => $schema->boolean(),
                'sortable' => $schema->boolean(),
                'filter' => $schema->boolean(),
                'required' => $schema->boolean(),
                'options' => $schema->object()->description('Map of select option value => label; required for select.'),
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
            'allow_delete' => $schema->boolean()->description('Enable delete action; defaults false.'),
        ];
    }

    protected function respond(callable $callback): Response
    {
        try {
            return Response::json(value($callback));
        } catch (\Throwable $exception) {
            return Response::error($exception->getMessage());
        }
    }
}
