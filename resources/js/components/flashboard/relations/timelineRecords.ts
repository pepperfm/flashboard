import type { RelationRecordShape } from './types'

export function appendTimelineRecords(current: RelationRecordShape[], incoming: RelationRecordShape[]): RelationRecordShape[] {
  const records = new Map(current.map(record => [String(record.key), record]))
  for (const record of incoming) {
    if (!records.has(String(record.key))) {
      records.set(String(record.key), record)
    }
  }
  return [...records.values()]
}
