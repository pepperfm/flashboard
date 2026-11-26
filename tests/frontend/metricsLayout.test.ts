import { expect, test } from 'bun:test'
import { parse } from '@vue/compiler-sfc'

test('all metric cards use the declared order in one non-stretching grid', async () => {
  const component = await Bun.file(new URL('../../resources/js/components/flashboard/metrics/FBMetrics.vue', import.meta.url)).text()
  const { descriptor, errors } = parse(component)
  expect(errors).toEqual([])
  const template = descriptor.template?.content ?? ''
  expect(template.match(/v-for="metric in metrics.items"/g)?.length).toBe(1)
  expect(template).not.toContain('v-for="metric in scalars"')
  expect(template).not.toContain('v-for="metric in charts"')
  expect(template).toContain('grid-cols-1 items-start gap-4 xl:grid-cols-12')
  expect(template).toContain('spanClass(metric)')
})
