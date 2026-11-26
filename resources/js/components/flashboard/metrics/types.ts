export type MetricPoint = {
  label: string
  value: number
}

export type MetricItem = {
  key: string
  kind: 'count' | 'sum' | 'avg' | 'trend' | 'breakdown'
  label: string
  resource: string
  chart: 'line' | 'area' | 'bar' | 'donut'
  span?: number
  value?: number | string | null
  points?: MetricPoint[]
}

export type MetricsPayload = {
  period_days: number
  items: MetricItem[]
}
