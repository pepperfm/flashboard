import { expect, test } from 'bun:test'
import { isScalarMetric, metricValueLabel } from '../../resources/js/components/flashboard/metrics/metricValue'
import type { MetricItem } from '../../resources/js/components/flashboard/metrics/types'

const metric: MetricItem = { key: 'total', kind: 'sum', label: 'Total', resource: 'Orders', chart: 'line' }

test('numeric cards preserve decimal strings without float conversion', () => {
  expect(metricValueLabel({ ...metric, value: '9007199254740993.1250' })).toBe('9007199254740993.1250')
  expect(metricValueLabel({ ...metric, value: '0' })).toBe('0')
  expect(metricValueLabel({ ...metric, kind: 'avg', value: null })).toBe('—')
  expect(isScalarMetric(metric)).toBe(true)
  expect(isScalarMetric({ ...metric, kind: 'avg' })).toBe(true)
  expect(isScalarMetric({ ...metric, kind: 'trend' })).toBe(false)
})
