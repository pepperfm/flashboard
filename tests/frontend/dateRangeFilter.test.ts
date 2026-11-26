import { expect, test } from 'bun:test'
import { writeDateRangeQuery } from '../../resources/js/components/flashboard/table/dateRangeFilter'

test('range updates preserve other filters and reset pagination', () => {
  const url = new URL('https://example.test/?page=5&filters[status]=draft&filters[period]=old')
  writeDateRangeQuery(url, 'period', { start: '2026-03-01', end: '2026-03-31' })
  expect(url.searchParams.get('filters[period][start]')).toBe('2026-03-01')
  expect(url.searchParams.get('filters[period][end]')).toBe('2026-03-31')
  expect(url.searchParams.get('filters[period]')).toBeNull()
  expect(url.searchParams.get('filters[status]')).toBe('draft')
  expect(url.searchParams.has('page')).toBe(false)
  writeDateRangeQuery(url, 'period', null)
  expect(url.searchParams.has('filters[period][start]')).toBe(false)
  expect(url.searchParams.has('filters[period][end]')).toBe(false)
})
