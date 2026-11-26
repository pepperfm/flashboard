import { expect, test } from 'bun:test'
import { parse } from '@vue/compiler-sfc'
import { breakdownRows, dashboardGroups } from '../../resources/js/components/flashboard/metrics/metricsLayout'
import type { MetricItem } from '../../resources/js/components/flashboard/metrics/types'

const metric = (key: string, kind: MetricItem['kind'], span: number): MetricItem => ({
  key, kind, span, resource: 'Example', label: key, chart: 'line',
})

test('dashboard groups by kind without mutating resource spans or declaration order', () => {
  const items = [metric('a', 'count', 3), metric('b', 'trend', 8), metric('c', 'breakdown', 4),
    metric('d', 'sum', 5), metric('e', 'avg', 12), metric('f', 'trend', 6)]
  const before = structuredClone(items)
  expect(dashboardGroups(items).map(group => [group.key, group.items.map(item => item.key)])).toEqual([
    ['summary', ['a', 'd', 'e']], ['trends', ['b', 'f']], ['breakdowns', ['c']],
  ])
  expect(items).toEqual(before)
  expect(dashboardGroups([])).toEqual([])
  expect(dashboardGroups([items[2]!]).map(group => group.key)).toEqual(['breakdowns'])
})

test('breakdown shares describe displayed groups including singleton and empty data', () => {
  expect(breakdownRows([{ label: 'Active', value: 5 }])).toEqual([{ label: 'Active', value: 5, share: 100 }])
  expect(breakdownRows([{ label: 'Active', value: 3 }, { label: 'Inactive', value: 1 }]).map(row => row.share)).toEqual([75, 25])
  expect(breakdownRows([{ label: 'Empty', value: 0 }])[0]?.share).toBe(0)
  expect(breakdownRows([])).toEqual([])
})

test('resource cards retain their own non-stretching span grid and only dashboard opts into compact layout', async () => {
  const base = new URL('../../resources/js/components/flashboard/', import.meta.url)
  const { descriptor, errors } = parse(await Bun.file(new URL('metrics/FBMetrics.vue', base)).text())
  expect(errors).toEqual([])
  const template = descriptor.template?.content ?? ''
  expect(template).toContain('v-for="metric in metrics.items"')
  expect(template).toContain('grid-cols-1 items-start gap-4 xl:grid-cols-12')
  expect(template).toContain('spanClass(metric)')
  expect(template).toContain('v-for="metric in group.items"')
  const screen = await Bun.file(new URL('FlashboardScreenContent.vue', base)).text()
  expect(screen.match(/mode="dashboard"/g)?.length).toBe(1)
})
