import type { MetricItem, MetricPoint } from './types'
import { isScalarMetric } from './metricValue'

export type MetricsMode = 'resource' | 'dashboard'

export function dashboardGroups(items: MetricItem[]) {
  const groups = [
    { key: 'summary', label: 'Overview', items: items.filter(isScalarMetric) },
    { key: 'trends', label: 'Activity over time', items: items.filter(item => item.kind === 'trend') },
    { key: 'breakdowns', label: 'Breakdowns', items: items.filter(item => item.kind === 'breakdown') },
  ] satisfies { key: 'summary' | 'trends' | 'breakdowns'; label: string; items: MetricItem[] }[]
  return groups.filter(group => group.items.length > 0)
}

export function breakdownRows(points: MetricPoint[]) {
  const total = points.reduce((sum, point) => sum + point.value, 0)
  return points.map(point => ({
    ...point,
    share: total > 0 ? point.value / total * 100 : 0,
  }))
}
