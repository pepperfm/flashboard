import { router } from '@inertiajs/vue3'
import { computed, onScopeDispose, reactive, ref, watch, type ComputedRef } from 'vue'
import { isDateRange, writeDateRangeQuery, type DateRangeValue } from './dateRangeFilter'
import type { TableColumnShape, TableFilterModelValue, TableFilterOptionShape, TableFilterOptionValue, TableFilterShape, TableSortDirection, TableStateDataset } from './types'

const TABLE_INPUT_FILTER_AUTOSUBMIT_DELAY_MS = 1000

type TableNavigation = {
  tableStateUrl: () => URL | null
  visitTableState: (url: URL) => void
}

function tableStateUrl(): URL | null {
  if (typeof window === 'undefined') {
    return null
  }

  return new URL(window.location.href)
}

function visitTableState(url: URL) {
  router.get(
    url.toString(),
    {},
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
    },
  )
}

export function useTableState(
  tableDataset: ComputedRef<TableStateDataset | undefined>,
  navigation: TableNavigation = { tableStateUrl, visitTableState },
) {
  const { tableStateUrl, visitTableState } = navigation
  const tableInputFilterTimers = new Map<string, ReturnType<typeof setTimeout>>()
  const tableInputFilterValues = reactive<Record<string, string>>({})
  const tableFilterPopoverOpen = reactive<Record<string, boolean>>({})
  const tableFilterSearchTerms = reactive<Record<string, string>>({})
  const tableSearchValue = ref('')
  let tableSearchTimer: ReturnType<typeof setTimeout> | null = null

  const dataTableColumns = computed(() => tableDataset.value?.columns ?? [])
  const searchableTableColumns = computed(() => dataTableColumns.value.filter((column) => column.searchable === true))
  const hasSearchableTableColumns = computed(() => searchableTableColumns.value.length > 0)
  const activeTableSearch = computed(() => String(tableDataset.value?.search ?? ''))
  const activeTableSort = computed(() => String(tableDataset.value?.sort ?? ''))
  const activeTableDirection = computed<TableSortDirection>(() => tableDataset.value?.direction === 'desc' ? 'desc' : 'asc')
  const tableFilters = computed(() => tableDataset.value?.filters ?? [])
  const hasTableFilters = computed(() => tableFilters.value.length > 0)
  const hasActiveTableFilters = computed(() =>
    Object.values(tableDataset.value?.active_filters ?? {})
      .some((value) => isDateRange(value) || tableFilterModelHasValue(value as TableFilterModelValue)),
  )
  const hasActiveTableSearch = computed(() => activeTableSearch.value.trim() !== '')

  watch(
    () => [
      tableDataset.value?.active_filters,
      tableDataset.value?.filters,
    ],
    () => syncTableInputFilterValues(),
    { deep: true, immediate: true },
  )

  watch(
    () => tableDataset.value?.search,
    () => syncTableSearchValue(),
    { immediate: true },
  )

  onScopeDispose(() => {
    for (const timer of tableInputFilterTimers.values()) {
      clearTimeout(timer)
    }

    tableInputFilterTimers.clear()
    clearTableSearchTimer()
  })

  function sortDirectionForColumn(column: TableColumnShape): TableSortDirection | null {
    if (column.sortable !== true || activeTableSort.value !== column.key) {
      return null
    }

    return activeTableDirection.value
  }

  function nextSortDirectionForColumn(column: TableColumnShape): TableSortDirection | null {
    const direction = sortDirectionForColumn(column)

    if (direction === null) {
      return 'asc'
    }

    if (direction === 'asc') {
      return 'desc'
    }

    return null
  }

  function visitPage(page: number) {
    const url = tableStateUrl()

    if (url === null) {
      return
    }

    if (page <= 1) {
      url.searchParams.delete('page')
    } else {
      url.searchParams.set('page', String(page))
    }

    visitTableState(url)
  }

  function activeTableFilterValue(filterKey: string): TableFilterModelValue {
    const value = tableDataset.value?.active_filters?.[filterKey]

    if (isDateRange(value) || !tableFilterModelHasValue(value)) {
      return null
    }

    return value
  }

  function activeDateRange(filterKey: string): DateRangeValue | null {
    const value = tableDataset.value?.active_filters?.[filterKey]
    return isDateRange(value) ? value : null
  }

  function updateDateRange(filterKey: string, value: DateRangeValue | null) {
    const url = tableStateUrl()
    if (url === null) {
      return
    }

    writeDateRangeQuery(url, filterKey, value)
    visitTableState(url)
  }

  function activeTableFilterScalarValue(filterKey: string): TableFilterOptionValue | null {
    return normalizeTableFilterModelValue(activeTableFilterValue(filterKey))[0] ?? null
  }

  function syncTableInputFilterValues() {
    const inputFilterKeys = new Set<string>()

    for (const filter of tableFilters.value) {
      if (filter.type !== 'input') {
        continue
      }

      inputFilterKeys.add(filter.key)
      tableInputFilterValues[filter.key] = String(activeTableFilterScalarValue(filter.key) ?? '')
    }

    for (const filterKey of Object.keys(tableInputFilterValues)) {
      if (!inputFilterKeys.has(filterKey)) {
        clearTableInputFilterTimer(filterKey)
        delete tableInputFilterValues[filterKey]
      }
    }
  }

  function updateInputFilterDraft(filterKey: string, value: TableFilterOptionValue | null | undefined) {
    tableInputFilterValues[filterKey] = value === null || value === undefined ? '' : String(value)
    scheduleTableInputFilterApply(filterKey)
  }

  function scheduleTableInputFilterApply(filterKey: string) {
    clearTableInputFilterTimer(filterKey)

    tableInputFilterTimers.set(
      filterKey,
      setTimeout(() => {
        tableInputFilterTimers.delete(filterKey)
        applyTableInputFilter(filterKey)
      }, TABLE_INPUT_FILTER_AUTOSUBMIT_DELAY_MS),
    )
  }

  function clearTableInputFilterTimer(filterKey: string) {
    const timer = tableInputFilterTimers.get(filterKey)

    if (timer === undefined) {
      return
    }

    clearTimeout(timer)
    tableInputFilterTimers.delete(filterKey)
  }

  function applyTableInputFilter(filterKey: string) {
    clearTableInputFilterTimer(filterKey)

    const nextValue = (tableInputFilterValues[filterKey] ?? '').trim()
    const currentValue = String(activeTableFilterScalarValue(filterKey) ?? '')

    if (nextValue === currentValue) {
      tableInputFilterValues[filterKey] = currentValue

      return
    }

    updateTableFilter(filterKey, nextValue)
  }

  function syncTableSearchValue() {
    tableSearchValue.value = activeTableSearch.value
  }

  function updateTableSearchDraft(value: string | number | null | undefined) {
    tableSearchValue.value = value === null || value === undefined ? '' : String(value)
    scheduleTableSearchApply()
  }

  function scheduleTableSearchApply() {
    clearTableSearchTimer()

    tableSearchTimer = setTimeout(() => {
      tableSearchTimer = null
      applyTableSearch()
    }, TABLE_INPUT_FILTER_AUTOSUBMIT_DELAY_MS)
  }

  function clearTableSearchTimer() {
    if (tableSearchTimer === null) {
      return
    }

    clearTimeout(tableSearchTimer)
    tableSearchTimer = null
  }

  function applyTableSearch() {
    clearTableSearchTimer()

    const nextValue = tableSearchValue.value.trim()
    const currentValue = activeTableSearch.value.trim()

    if (nextValue === currentValue) {
      tableSearchValue.value = currentValue

      return
    }

    updateTableSearch(nextValue)
  }

  function clearTableSearch() {
    tableSearchValue.value = ''
    updateTableSearch('')
  }

  function normalizeTableFilterOptions(filter: TableFilterShape): TableFilterOptionShape[] {
    const options = filter.options ?? []

    const normalizedOptions = Array.isArray(options)
      ? options.map((option) => (
          typeof option === 'object' && option !== null && 'value' in option
            ? option
            : { label: String(option), value: option as TableFilterOptionValue }
        ))
      : Object.entries(options).map(([value, label]) => ({
          label: String(label),
          value,
        }))

    if (filter.multiple === true) {
      return normalizedOptions
    }

    return [
      {
        label: `All ${filter.label ?? filter.key}`,
        value: '',
      },
      ...normalizedOptions,
    ]
  }

  function searchableTableFilterOptions(filter: TableFilterShape): TableFilterOptionShape[] {
    const options = normalizeTableFilterOptions(filter)
    const searchTerm = (tableFilterSearchTerms[filter.key] ?? '').trim().toLowerCase()

    if (searchTerm === '') {
      return options
    }

    return options.filter((option) =>
      String(option.label ?? '').toLowerCase().includes(searchTerm)
      || String(option.value ?? '').toLowerCase().includes(searchTerm),
    )
  }

  function selectedTableFilterLabel(filter: TableFilterShape): string | null {
    const selectedValues = normalizeTableFilterModelValue(activeTableFilterValue(filter.key))

    if (selectedValues.length === 0) {
      return null
    }

    const options = normalizeTableFilterOptions(filter)
    const selectedLabels = selectedValues.map((selectedValue) => {
      const selectedOption = options
        .find((option) => String(option.value ?? '') === String(selectedValue))

      return selectedOption?.label ? String(selectedOption.label) : String(selectedValue)
    })

    if (selectedLabels.length > 2) {
      return `${selectedLabels.length} selected`
    }

    return selectedLabels.join(', ')
  }

  function selectTableFilterOption(filter: TableFilterShape, value: TableFilterOptionValue | null | undefined) {
    if (filter.multiple === true) {
      if (!tableFilterScalarHasValue(value)) {
        tableFilterPopoverOpen[filter.key] = false
        updateTableFilter(filter.key, [])

        return
      }

      const currentValues = normalizeTableFilterModelValue(activeTableFilterValue(filter.key))
      const nextValues = isTableFilterOptionSelected(filter, value)
        ? currentValues.filter((currentValue) => String(currentValue) !== String(value))
        : [...currentValues, value]

      updateTableFilter(filter.key, nextValues)

      return
    }

    tableFilterPopoverOpen[filter.key] = false
    updateTableFilter(filter.key, value)
  }

  function tableFilterOptionClass(filter: TableFilterShape, option: TableFilterOptionShape): Record<string, boolean> {
    return {
      'table-filter-option': true,
      'table-filter-option--active': isTableFilterOptionSelected(filter, option.value),
    }
  }

  function updateTableFilter(filterKey: string, value: TableFilterModelValue) {
    const url = tableStateUrl()

    if (url === null) {
      return
    }

    tableFilterSearchTerms[filterKey] = ''

    deleteTableFilterQueryParams(url, filterKey)

    if (Array.isArray(value)) {
      for (const item of normalizeTableFilterModelValue(value)) {
        url.searchParams.append(`filters[${filterKey}][]`, String(item))
      }
    } else if (tableFilterScalarHasValue(value)) {
      url.searchParams.set(`filters[${filterKey}]`, String(value))
    }

    url.searchParams.delete('page')

    visitTableState(url)
  }

  function updateTableSearch(value: string) {
    const url = tableStateUrl()

    if (url === null) {
      return
    }

    if (value === '') {
      url.searchParams.delete('search')
    } else {
      url.searchParams.set('search', value)
    }

    url.searchParams.delete('page')
    visitTableState(url)
  }

  function toggleTableSort(column: TableColumnShape) {
    if (column.sortable !== true) {
      return
    }

    const url = tableStateUrl()

    if (url === null) {
      return
    }

    const nextDirection = nextSortDirectionForColumn(column)

    if (nextDirection === null) {
      url.searchParams.delete('sort')
      url.searchParams.delete('direction')
    } else {
      url.searchParams.set('sort', column.key)
      url.searchParams.set('direction', nextDirection)
    }

    url.searchParams.delete('page')
    visitTableState(url)
  }

  function deleteTableFilterQueryParams(url: URL, filterKey: string) {
    const scalarKey = `filters[${filterKey}]`
    const arrayKeyPrefix = `filters[${filterKey}][`

    for (const key of Array.from(url.searchParams.keys())) {
      if (key === scalarKey || key.startsWith(arrayKeyPrefix)) {
        url.searchParams.delete(key)
      }
    }
  }

  function normalizeTableFilterModelValue(value: TableFilterModelValue): TableFilterOptionValue[] {
    if (!Array.isArray(value)) {
      return tableFilterScalarHasValue(value) ? [value] : []
    }

    const valuesByKey = new Map<string, TableFilterOptionValue>()

    for (const item of value) {
      if (tableFilterScalarHasValue(item)) {
        valuesByKey.set(String(item), item)
      }
    }

    return Array.from(valuesByKey.values())
  }

  function tableFilterModelHasValue(value: TableFilterModelValue): boolean {
    return normalizeTableFilterModelValue(value).length > 0
  }

  function tableFilterScalarHasValue(value: TableFilterOptionValue | null | undefined): value is TableFilterOptionValue {
    return value !== null && value !== undefined && value !== ''
  }

  function isTableFilterOptionSelected(filter: TableFilterShape, value: TableFilterOptionValue | null | undefined): boolean {
    if (!tableFilterScalarHasValue(value)) {
      return !tableFilterModelHasValue(activeTableFilterValue(filter.key))
    }

    return normalizeTableFilterModelValue(activeTableFilterValue(filter.key))
      .some((selectedValue) => String(selectedValue) === String(value))
  }

  function resetTableFilters() {
    const url = tableStateUrl()

    if (url === null) {
      return
    }

    Array.from(url.searchParams.keys())
      .filter((key) => key.startsWith('filters[') || key === 'filters')
      .forEach((key) => url.searchParams.delete(key))

    url.searchParams.delete('page')

    visitTableState(url)
  }

  return {
    dataTableColumns, hasSearchableTableColumns, tableFilters, hasTableFilters,
    hasActiveTableFilters, hasActiveTableSearch, tableInputFilterValues,
    tableFilterPopoverOpen, tableFilterSearchTerms, tableSearchValue,
    sortDirectionForColumn, nextSortDirectionForColumn, toggleTableSort, visitPage,
    activeTableFilterValue, activeDateRange, updateDateRange, activeTableFilterScalarValue,
    updateInputFilterDraft, applyTableInputFilter, updateTableSearchDraft,
    applyTableSearch, clearTableSearch, normalizeTableFilterOptions,
    searchableTableFilterOptions, selectedTableFilterLabel, selectTableFilterOption,
    tableFilterOptionClass, updateTableFilter, isTableFilterOptionSelected, resetTableFilters,
  }
}
