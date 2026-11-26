import type { DateRangeValue } from './dateRangeFilter'

export type TableColumnShape = {
  format?: string
  key: string
  label: string
  searchable?: boolean
  sortable?: boolean
  type?: string
}

export type TableFilterOptionValue = string | number | boolean
export type TableFilterModelValue = TableFilterOptionValue | TableFilterOptionValue[] | null | undefined

export type TableFilterOptionShape = {
  label?: string
  value?: TableFilterOptionValue
}

export type TableFilterMatchMode = 'contains' | 'exact'

export type TableFilterShape = {
  key: string
  lazy?: boolean
  label?: string
  match?: TableFilterMatchMode
  multiple?: boolean
  options?: TableFilterOptionShape[] | Record<string, TableFilterOptionValue>
  options_per_page?: number
  options_url?: string
  searchable?: boolean
  type?: string
}

export type TableSortDirection = 'asc' | 'desc'

export type TableStateDataset = {
  active_filters?: Record<string, TableFilterModelValue | DateRangeValue>
  columns?: TableColumnShape[]
  direction?: TableSortDirection | string
  filters?: TableFilterShape[]
  search?: string
  sort?: string
}
