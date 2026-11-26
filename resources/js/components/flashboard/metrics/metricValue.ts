import type { MetricItem } from './types'

export function isScalarMetric(metric: MetricItem): boolean {
  return metric.kind === 'count' || metric.kind === 'sum' || metric.kind === 'avg'
}

export function metricValueLabel(metric: MetricItem): string {
  if (metric.kind === 'count') {
    return new Intl.NumberFormat().format(typeof metric.value === 'number' ? metric.value : 0)
  }

  // Numeric aggregates arrive as database strings. Do not round through JS Number.
  return typeof metric.value === 'string' ? metric.value : '—'
}
