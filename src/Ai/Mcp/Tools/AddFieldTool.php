<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Ai\Mcp\Tools;

use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('add_field')]
#[Description('Preview adding a field to an unchanged AI-managed Resource; apply=true requires exact preview hashes.')]
final class AddFieldTool extends HarnessTool
{
    public function schema(\Illuminate\Contracts\JsonSchema\JsonSchema $schema): array
    {
        return [
            'resource' => $schema->string()->description('Existing AI-managed Resource name.')->required(),
            'field' => $schema->object([
                'name' => $schema->string()->description('Existing DB column and optional field properties (type, label, table, form, etc).')->required(),
                'type' => $schema->string(),
                'label' => $schema->string(),
                'table' => $schema->boolean(),
                'form' => $schema->boolean(),
                'detail' => $schema->boolean(),
                'required' => $schema->boolean(),
                'searchable' => $schema->boolean(),
                'sortable' => $schema->boolean(),
                'filter' => $schema->boolean(),
                'options' => $schema->object(),
            ])->required(),
            'apply' => $schema->boolean()->description('Defaults false: preview only. Set true only after user approves preview.'),
            'expected_sha' => $schema->string()->description('For apply=true: existing file SHA returned by preview.'),
            'proposal_hash' => $schema->string()->description('For apply=true: proposal SHA returned by preview.'),
        ];
    }

    public function handle(\Laravel\Mcp\Request $request): \Laravel\Mcp\Response
    {
        return $this->respond(static function () use ($request): array {
            $manager = app(\Pepperfm\Flashboard\Ai\ResourceChangeManager::class);
            $resource = (string) $request->get('resource', '');
            $definition = $request->get('field', []);

            if (!is_array($definition)) {
                throw new \InvalidArgumentException('field must be an object.');
            }

            if ($request->get('apply', false) === true) {
                return $manager->addField(
                    $resource,
                    $definition,
                    (string) $request->get('expected_sha', ''),
                    (string) $request->get('proposal_hash', ''),
                );
            }

            return $manager->previewField($resource, $definition);
        });
    }
}
