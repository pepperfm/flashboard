<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Ai\Inspection;

use Pepperfm\Flashboard\Core\Runtime\Payloads\SchemaVersion;

final class CapabilityCatalog
{
    /**
     * @return array<string, mixed>
     */
    public function describe(): array
    {
        return [
            'package_version' => PackageVersion::current(),
            'payload_schema' => SchemaVersion::V1->value,
            'transport' => 'stdio',
            'features' => [
                'resource_inspection' => 'registered',
                'fields' => [
                    'text', 'textarea', 'number', 'date', 'select', 'checkbox', 'toggle', 'enum',
                ],
                'generation' => [
                    'relations' => ['belongs_to', 'belongs_to_many', 'has_one', 'has_many'],
                    'filters' => ['input', 'select', 'date', 'date_range'],
                    'requires_editable_field' => true,
                    'read_only_resources' => true,
                    'enum_options' => true,
                    'metrics' => ['count', 'trend', 'breakdown', 'sum', 'avg'],
                    'record_actions' => false,
                ],
                'manual_dsl' => [
                    'metrics' => ['count', 'trend', 'breakdown', 'sum', 'avg'],
                    'scoped_actions' => true,
                    'action_forms' => true,
                    'read_only_resources' => true,
                    'enum_breakdown_labels' => true,
                    'custom_pages' => true,
                    'relation_field_authorization' => true,
                ],
            ],
            'write_safety' => [
                'generated_resources_only' => true,
                'preview_hash_required' => true,
                'update_expected_sha_required' => true,
                'business_record_access' => false,
            ],
        ];
    }
}
