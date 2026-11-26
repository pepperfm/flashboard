import { expect, test } from 'bun:test'
import { appendTimelineRecords } from '../../resources/js/components/flashboard/relations/timelineRecords'

test('timeline pagination preserves chronological server order and deduplicates overlapping pages', () => {
  const first = [{ key: 3, title: 'Third' }, { key: 2, title: 'Second' }]
  expect(appendTimelineRecords(first, [{ key: '2', title: 'Duplicate' }, { key: 1, title: 'First' }])).toEqual([
    ...first, { key: 1, title: 'First' },
  ])
  expect(first).toHaveLength(2)
})
