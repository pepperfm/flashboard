export type MetricPoint = {
  label: string
  value: number
}

export type MetricItem = {
  key: string
  kind: 'count' | 'trend' | 'breakdown'
  label: string
  resource: string
  chart: 'line' | 'area' | 'bar' | 'donut'
  value?: number
  points?: MetricPoint[]
}

export type MetricsPayload = {
  period_days: number
  items: MetricItem[]
}
