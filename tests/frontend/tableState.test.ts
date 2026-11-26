import { expect, test } from 'bun:test'
import { computed, effectScope, nextTick, ref } from 'vue'
import { useTableState } from '../../resources/js/components/flashboard/table/useTableState'
import type { TableStateDataset } from '../../resources/js/components/flashboard/table/types'

function setup(dataset: TableStateDataset, initial = 'https://example.test/items?page=3&filters[other]=keep') {
  const scope = effectScope()
  const data = ref(dataset)
  let current = new URL(initial)
  const visits: URL[] = []
  const state = scope.run(() => useTableState(computed(() => data.value), {
    tableStateUrl: () => new URL(current),
    visitTableState: (url) => { visits.push(url); current = url },
  }))!
  return { scope, data, visits, state }
}

test('table filters preserve unrelated query values, retain falsy values and reset pagination', () => {
  const { scope, state, visits } = setup({ active_filters: { status: [0, false], period: { start: '2026-03-01', end: '2026-03-31' } } })
  try {
    expect(state.activeTableFilterValue('status')).toEqual([0, false])
    expect(state.activeTableFilterValue('period')).toBeNull()
    expect(state.activeDateRange('period')?.start).toBe('2026-03-01')
    state.updateTableFilter('status', [0, false, 0, ''])
    expect(visits[0]?.searchParams.getAll('filters[status][]')).toEqual(['0', 'false'])
    expect(visits[0]?.searchParams.get('filters[other]')).toBe('keep')
    expect(visits[0]?.searchParams.has('page')).toBe(false)
    state.updateDateRange('period', { start: '2026-04-01', end: '2026-04-30' })
    expect(visits[1]?.searchParams.get('filters[period][end]')).toBe('2026-04-30')
    state.resetTableFilters()
    expect([...visits[2]!.searchParams.keys()]).toEqual([])
  } finally { scope.stop() }
})

test('sort cycles ascending, descending and cleared; page navigation preserves filters', async () => {
  const column = { key: 'name', label: 'Name', sortable: true }
  const { scope, data, state, visits } = setup({ columns: [column] })
  try {
    state.toggleTableSort(column)
    expect(visits[0]?.searchParams.get('direction')).toBe('asc')
    data.value = { ...data.value, sort: 'name', direction: 'asc' }
    state.toggleTableSort(column)
    expect(visits[1]?.searchParams.get('direction')).toBe('desc')
    data.value = { ...data.value, direction: 'desc' }
    state.toggleTableSort(column)
    expect(visits[2]?.searchParams.has('sort')).toBe(false)
    state.visitPage(2)
    expect(visits[3]?.searchParams.get('page')).toBe('2')
    expect(visits[3]?.searchParams.get('filters[other]')).toBe('keep')
    state.visitPage(1)
    expect(visits[4]?.searchParams.has('page')).toBe(false)
  } finally { scope.stop() }
})

test('draft synchronization, immediate submission and disposal cancel pending table visits', async () => {
  const { scope, data, state, visits } = setup({ search: 'old', filters: [{ key: 'name', type: 'input' }], active_filters: { name: 'before' } })
  try {
    expect(state.tableSearchValue.value).toBe('old')
    expect(state.tableInputFilterValues.name).toBe('before')
    state.updateTableSearchDraft(' new ')
    state.applyTableSearch()
    expect(visits[0]?.searchParams.get('search')).toBe('new')
    data.value = { ...data.value, search: 'new', active_filters: { name: 'after' } }
    await nextTick()
    expect(state.tableInputFilterValues.name).toBe('after')
    state.updateInputFilterDraft('name', 'queued')
    state.updateTableSearchDraft('queued')
  } finally { scope.stop() }
  await Bun.sleep(1100)
  expect(visits).toHaveLength(1)
})
