import { expect, test } from 'bun:test'
import {
  resolveFormContainerLayout,
  resolveFormItemLayout,
} from '../../resources/js/components/flashboard/forms/layout/resolveFormLayout'

test('form grids apply responsive columns and clamp field spans', () => {
  const grid = resolveFormContainerLayout({
    mode: 'grid',
    columns: { default: 1, md: 3 },
    gap: { default: 2, lg: 6 },
  })

  expect(grid.columns).toEqual({
    default: 1,
    sm: 1,
    md: 3,
    lg: 3,
    xl: 3,
    '2xl': 3,
  })
  expect(grid.style['--fb-form-layout-gap-md']).toBe('calc(2 * 0.25rem)')
  expect(grid.style['--fb-form-layout-gap-lg']).toBe('calc(6 * 0.25rem)')

  const field = resolveFormItemLayout(grid, {
    column_span: { default: 'full', md: 2, lg: 5 },
  })

  expect(field.style['--fb-form-layout-span-default']).toBe('1')
  expect(field.style['--fb-form-layout-span-md']).toBe('2')
  expect(field.style['--fb-form-layout-span-lg']).toBe('3')
})

test('flex form settings preserve alignment and ignore grid-only spans', () => {
  const flex = resolveFormContainerLayout({
    mode: 'flex',
    direction: 'column',
    justify: 'between',
    align: 'center',
    wrap: false,
  })

  expect(flex.mode).toBe('flex')
  expect(flex.style['--fb-form-layout-direction-default']).toBe('column')
  expect(flex.style['--fb-form-layout-justify-default']).toBe('space-between')
  expect(flex.style['--fb-form-layout-align-default']).toBe('center')
  expect(flex.style['--fb-form-layout-wrap-default']).toBe('nowrap')
  expect(resolveFormItemLayout(flex, { column_span: 'full' }).style).toEqual({})
})
