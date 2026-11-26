import { expect, test } from 'bun:test'
import { relationSyncKeys, relationSyncOptions } from '../../resources/js/components/flashboard/relations/relationSyncSelection'

test('sync is unavailable while current relation membership is paginated', () => {
  const records = [{ key: 1, title: 'First' }, { key: 2, title: 'Second' }]
  expect(relationSyncKeys({ records, pagination: { has_more: true } })).toBeNull()
  expect(relationSyncKeys({ records, pagination: { has_more: false } })).toEqual([1, 2])
})

test('current relation members stay selectable even when attach options exclude them', () => {
  expect(relationSyncOptions([{ key: 1, title: 'Existing' }], [{ value: 2, label: 'New' }])).toEqual([
    { value: 1, label: 'Existing' }, { value: 2, label: 'New' },
  ])
})
