type FormLayoutMode = 'stack' | 'grid' | 'flex'
type FormLayoutBreakpoint = 'default' | 'sm' | 'md' | 'lg' | 'xl' | '2xl'
type FormFlexDirection = 'row' | 'column'
type FormFlexJustify = 'start' | 'center' | 'end' | 'between'
type FormFlexAlign = 'start' | 'center' | 'end' | 'stretch'
type FormLayoutResponsiveNumber = number | Partial<Record<FormLayoutBreakpoint, number>>
type FormLayoutResponsiveSpanValue = number | 'full'
type FormLayoutResponsiveSpan = FormLayoutResponsiveSpanValue | Partial<Record<FormLayoutBreakpoint, FormLayoutResponsiveSpanValue>>

export const FORM_LAYOUT_BREAKPOINTS = ['default', 'sm', 'md', 'lg', 'xl', '2xl'] as const

export type FormContainerLayoutShape = {
  align?: FormFlexAlign
  columns?: Partial<Record<FormLayoutBreakpoint, number>>
  direction?: FormFlexDirection
  gap?: Partial<Record<FormLayoutBreakpoint, number>>
  justify?: FormFlexJustify
  mode?: FormLayoutMode
  wrap?: boolean
}

export type FormFieldLayoutShape = {
  column_span?: FormLayoutResponsiveSpan
}

export type FormContainerLayoutDefaults = {
  align?: FormFlexAlign
  columns?: FormLayoutResponsiveNumber
  direction?: FormFlexDirection
  gap?: FormLayoutResponsiveNumber
  justify?: FormFlexJustify
  mode?: FormLayoutMode
  wrap?: boolean
}

export type ResolvedFormContainerLayout = {
  className: string[]
  columns: Record<FormLayoutBreakpoint, number> | null
  mode: FormLayoutMode
  style: Record<string, string>
}

const FORM_LAYOUT_DEFAULT_GAP = 4
const FORM_LAYOUT_DEFAULT_STACK_GAP = 5
const FORM_LAYOUT_DEFAULT_FLEX_DIRECTION: FormFlexDirection = 'row'
const FORM_LAYOUT_DEFAULT_FLEX_JUSTIFY: FormFlexJustify = 'start'
const FORM_LAYOUT_DEFAULT_FLEX_ALIGN: FormFlexAlign = 'stretch'

function gapToCss(gap: number): string {
  return `calc(${gap} * 0.25rem)`
}

function justifyToCss(justify: FormFlexJustify): string {
  return {
    start: 'flex-start',
    center: 'center',
    end: 'flex-end',
    between: 'space-between',
  }[justify]
}

function alignToCss(align: FormFlexAlign): string {
  return {
    start: 'flex-start',
    center: 'center',
    end: 'flex-end',
    stretch: 'stretch',
  }[align]
}

function columnDefaults(columns: number): Partial<Record<FormLayoutBreakpoint, number>> {
  if (columns <= 1) {
    return { default: 1 }
  }

  return {
    default: 1,
    md: columns,
  }
}

function expandResponsiveNumber(
  value: FormLayoutResponsiveNumber | undefined,
  fallbackDefault: number,
  transformScalar?: (value: number) => Partial<Record<FormLayoutBreakpoint, number>>,
): Record<FormLayoutBreakpoint, number> {
  let seed: Partial<Record<FormLayoutBreakpoint, number>>

  if (typeof value === 'number') {
    seed = transformScalar ? transformScalar(value) : { default: value }
  } else {
    seed = value ?? {}
  }

  let current = seed.default ?? fallbackDefault

  return FORM_LAYOUT_BREAKPOINTS.reduce((map, breakpoint) => {
    current = seed[breakpoint] ?? current
    map[breakpoint] = current

    return map
  }, {} as Record<FormLayoutBreakpoint, number>)
}

function expandResponsiveSpan(
  value: FormLayoutResponsiveSpan | undefined,
): Record<FormLayoutBreakpoint, FormLayoutResponsiveSpanValue> {
  let seed: Partial<Record<FormLayoutBreakpoint, FormLayoutResponsiveSpanValue>>

  if (typeof value === 'number' || value === 'full') {
    seed = { default: value }
  } else {
    seed = value ?? {}
  }

  let current = seed.default ?? 1

  return FORM_LAYOUT_BREAKPOINTS.reduce((map, breakpoint) => {
    current = seed[breakpoint] ?? current
    map[breakpoint] = current

    return map
  }, {} as Record<FormLayoutBreakpoint, FormLayoutResponsiveSpanValue>)
}

export function resolveFormContainerLayout(
  layout?: FormContainerLayoutShape,
  defaults: FormContainerLayoutDefaults = {},
): ResolvedFormContainerLayout {
  const mode = layout?.mode ?? defaults.mode ?? 'stack'
  const gap = expandResponsiveNumber(
    layout?.gap ?? defaults.gap,
    mode === 'stack' ? FORM_LAYOUT_DEFAULT_STACK_GAP : FORM_LAYOUT_DEFAULT_GAP,
  )
  const style: Record<string, string> = {}
  const className = ['fb-form-layout', `fb-form-layout--${mode}`]

  for (const breakpoint of FORM_LAYOUT_BREAKPOINTS) {
    style[`--fb-form-layout-gap-${breakpoint}`] = gapToCss(gap[breakpoint])
  }

  if (mode === 'grid') {
    const columns = expandResponsiveNumber(layout?.columns ?? defaults.columns, 1, columnDefaults)

    for (const breakpoint of FORM_LAYOUT_BREAKPOINTS) {
      style[`--fb-form-layout-columns-${breakpoint}`] = String(columns[breakpoint])
    }

    return {
      className,
      columns,
      mode,
      style,
    }
  }

  if (mode === 'flex') {
    const direction = layout?.direction ?? defaults.direction ?? FORM_LAYOUT_DEFAULT_FLEX_DIRECTION
    const justify = layout?.justify ?? defaults.justify ?? FORM_LAYOUT_DEFAULT_FLEX_JUSTIFY
    const align = layout?.align ?? defaults.align ?? FORM_LAYOUT_DEFAULT_FLEX_ALIGN
    const wrap = layout?.wrap ?? defaults.wrap ?? true

    style['--fb-form-layout-direction-default'] = direction
    style['--fb-form-layout-justify-default'] = justifyToCss(justify)
    style['--fb-form-layout-align-default'] = alignToCss(align)
    style['--fb-form-layout-wrap-default'] = wrap ? 'wrap' : 'nowrap'

    return {
      className,
      columns: null,
      mode,
      style,
    }
  }

  return {
    className,
    columns: null,
    mode,
    style,
  }
}

export function resolveFormItemLayout(
  containerLayout: ResolvedFormContainerLayout,
  fieldLayout?: FormFieldLayoutShape,
): { className: string[]; style: Record<string, string> } {
  const className = ['fb-form-layout__item', `fb-form-layout__item--${containerLayout.mode}`]

  if (containerLayout.mode !== 'grid' || containerLayout.columns === null) {
    return {
      className,
      style: {},
    }
  }

  const spans = expandResponsiveSpan(fieldLayout?.column_span)
  const style: Record<string, string> = {}

  for (const breakpoint of FORM_LAYOUT_BREAKPOINTS) {
    const span = spans[breakpoint]
    const columns = containerLayout.columns[breakpoint]

    style[`--fb-form-layout-span-${breakpoint}`] = String(
      span === 'full' ? columns : Math.min(span, columns),
    )
  }

  return {
    className,
    style,
  }
}
