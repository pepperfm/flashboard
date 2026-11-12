<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Ai\Mcp\Tools;

use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('add_relation')]
#[Description('Preview adding a relation to an unchanged AI-managed Resource; apply=true requires exact preview hashes.')]
final class AddRelationTool extends HarnessTool
{
    public function schema(\Illuminate\Contracts\JsonSchema\JsonSchema $schema): array
    {
        return [
            'resource' => $schema->string()->description('Existing AI-managed Resource name.')->required(),
            'relation' => $schema->object([
                'name' => $schema->string()->description('Eloquent relationship name and type (belongs_to, belongs_to_many, has_one, has_many).')->required(),
                'type' => $schema->string()->description('belongs_to, belongs_to_many, has_one or has_many.')->required(),
                'label' => $schema->string(),
                'resource' => $schema->string()->description('Registered related Resource, optional if unambiguous.'),
                'title_attribute' => $schema->string(),
                'search_columns' => $schema->array()->items($schema->string()),
                'show_on_edit' => $schema->boolean(),
                'read_only' => $schema->boolean(),
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
            $definition = $request->get('relation', []);

            if (!is_array($definition)) {
                throw new \InvalidArgumentException('relation must be an object.');
            }

            if ($request->get('apply', false) === true) {
                return $manager->addRelation(
                    $resource,
                    $definition,
                    (string) $request->get('expected_sha', ''),
                    (string) $request->get('proposal_hash', ''),
                );
            }

            return $manager->previewRelation($resource, $definition);
        });
    }
}
