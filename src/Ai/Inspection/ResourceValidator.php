<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Ai\Inspection;

use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Forms\Builders\Form;
use Pepperfm\Flashboard\Core\Forms\Fields\Field;
use Pepperfm\Flashboard\Core\Forms\Relations\BelongsToRelationMetadataResolver;
use Pepperfm\Flashboard\Core\Forms\Relations\BelongsToManyRelationMetadataResolver;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Core\Relations\RelationManagerDefinition;
use Pepperfm\Flashboard\Integration\Laravel\Diagnostics\Diagnostic;
use Pepperfm\Flashboard\Integration\Laravel\Diagnostics\DiagnosticSeverity;
use Pepperfm\Flashboard\Integration\Laravel\Diagnostics\ResourceDiagnosticChecks;
use Pepperfm\Flashboard\Integration\Laravel\Relations\RelationManagerMetadataResolver;

final readonly class ResourceValidator
{
    public function __construct(
        private RegisteredResourceLocator $locator,
        private ResourceRegistry $registry,
        private ResourceDiagnosticChecks $diagnostics,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function validate(string $identifier): array
    {
        $resource = $this->locator->resolve($identifier);
        $checks = $this->diagnostics->inspectRegisteredResource($resource);
        $checks = [...$checks, ...$this->validateFields($resource), ...$this->validateRelations($resource)];

        return [
            'resource' => $resource,
            'valid' => !array_any($checks, static fn(Diagnostic $check): bool => $check->severity === DiagnosticSeverity::Error),
            'diagnostics' => array_map(static fn(Diagnostic $check): array => $check->toArray(), $checks),
            'runtime_authorization' => 'not_evaluated',
            'note' => 'Static declaration, schema and relation checks only. Dynamic callbacks and authorization outcomes require host tests.',
        ];
    }

    /**
     * @param class-string<Resource> $resource
     *
     * @return list<Diagnostic>
     */
    private function validateFields(string $resource): array
    {
        $modelClass = $resource::model();
        $model = new $modelClass();
        $columns = $model->getConnection()->getSchemaBuilder()->getColumnListing($model->getTable());
        $checks = [];

        foreach ($resource::form(Form::make())->fieldSchema() as $field) {
            $key = $field['key'] ?? null;
            $type = $field['type'] ?? null;

            if (!is_string($key) || $key === '') {
                $checks[] = new Diagnostic('field_key_missing', DiagnosticSeverity::Error, 'A form field has no key.', $resource);
                continue;
            }

            try {
                $metadata = match ($type) {
                    Field::TYPE_BELONGS_TO => new BelongsToRelationMetadataResolver($this->registry)->resolve($resource, $field),
                    Field::TYPE_BELONGS_TO_MANY => new BelongsToManyRelationMetadataResolver($this->registry)->resolve($resource, $field),
                    default => null,
                };

                if ($metadata !== null && $metadata->relatedResource === null && !$metadata->allowModelFallback) {
                    $checks[] = new Diagnostic('field_relation_resource_missing', DiagnosticSeverity::Error, "Field $key needs a related resource or explicit model fallback.", $resource);
                } elseif ($metadata === null && !in_array($key, $columns, true)) {
                    $checks[] = new Diagnostic('field_not_physical_column', DiagnosticSeverity::Warning, "Field $key is not a physical column; verify its host mutation or accessor.", $resource);
                }
            } catch (\Throwable) {
                $checks[] = new Diagnostic('field_relation_invalid', DiagnosticSeverity::Error, "Relation metadata could not be resolved for field $key.", $resource);
            }
        }

        return $checks;
    }

    /**
     * @param class-string<Resource> $resource
     *
     * @return list<Diagnostic>
     */
    private function validateRelations(string $resource): array
    {
        $checks = [];
        $resolver = new RelationManagerMetadataResolver($this->registry);

        foreach ($resource::relations() as $relation) {
            if (!$relation instanceof RelationManagerDefinition) {
                continue;
            }

            try {
                $resolver->resolve($resource, $relation->toArray());
            } catch (\Throwable) {
                $checks[] = new Diagnostic('relation_manager_invalid', DiagnosticSeverity::Error, 'A relation manager could not be resolved.', $resource);
            }
        }

        return $checks;
    }
}
