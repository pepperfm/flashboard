import type { RelationManagerPayload, RelationOptionShape, RelationRecordShape, RelationScalarValue } from './types'
import { hasRelationValue } from './types'

export function relationSyncKeys(relation: RelationManagerPayload): RelationScalarValue[] | null {
  if (relation.pagination?.has_more) {
    return null
  }

  return (relation.selected_records ?? relation.records ?? [])
    .map((record) => record.key)
    .filter(hasRelationValue)
}

export function relationSyncOptions(records: RelationRecordShape[], options: RelationOptionShape[]): RelationOptionShape[] {
  const items = new Map<string, RelationOptionShape>()
  for (const record of records) {
    items.set(String(record.key), { label: record.title, value: record.key })
  }
  for (const option of options) {
    if (hasRelationValue(option.value)) {
      items.set(String(option.value), option)
    }
  }
  return [...items.values()]
}
