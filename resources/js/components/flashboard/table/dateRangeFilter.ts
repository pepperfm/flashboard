export type DateRangeValue = { start: string, end: string }

export function isDateRange(value: unknown): value is DateRangeValue {
  return typeof value === 'object' && value !== null
    && 'start' in value && typeof value.start === 'string'
    && 'end' in value && typeof value.end === 'string'
}

export function writeDateRangeQuery(url: URL, key: string, value: DateRangeValue | null): void {
  const prefix = `filters[${key}]`
  for (const name of Array.from(url.searchParams.keys())) {
    if (name === prefix || name.startsWith(prefix + '[')) {
      url.searchParams.delete(name)
    }
  }

  if (value) {
    url.searchParams.set(prefix + '[start]', value.start)
    url.searchParams.set(prefix + '[end]', value.end)
  }

  url.searchParams.delete('page')
}
