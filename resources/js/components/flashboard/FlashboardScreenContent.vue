<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import { useToast } from '@nuxt/ui/composables'
import SimpleFormShell from '@/components/flashboard/forms/layout/SimpleFormShell.vue'
import DatePickerFilter from '@/components/flashboard/table/DatePickerFilter.vue'
import LazySelectFilter from '@/components/flashboard/table/LazySelectFilter.vue'
import type { FormContainerLayoutShape } from '@/components/flashboard/forms/layout/resolveFormLayout'
import type { FormFieldShape, FormNodeShape } from '@/components/flashboard/forms/renderers/resolveFormFieldRenderer'
import { computed, h, onBeforeUnmount, reactive, ref, resolveComponent, watch } from 'vue'

type ActionShape = {
  color?: string | null
  icon?: string | null
  kind?: string
  key: string
  label?: string
  method?: string
  requires_confirmation?: boolean
  success_message?: string | null
  url?: string
  visible?: boolean
}

type TableColumnShape = {
  format?: string
  key: string
  label: string
  searchable?: boolean
  sortable?: boolean
  type?: string
}

type TableFilterOptionValue = string | number | boolean
type TableFilterModelValue = TableFilterOptionValue | TableFilterOptionValue[] | null | undefined

type TableFilterOptionShape = {
  label?: string
  value?: TableFilterOptionValue
}

type TableFilterMatchMode = 'contains' | 'exact'

type TableFilterShape = {
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

type FormGroupShape = {
  key: string
  label?: string
  description?: string
  icon?: string
  layout?: FormContainerLayoutShape
  schema?: FormFieldShape[]
}

type DetailEntryShape = {
  key: string
  label?: string
  type?: string
  help?: string
  value?: unknown
}

type DetailGroupShape = {
  key: string
  label?: string
  description?: string
  schema?: DetailEntryShape[]
}

type PayloadShape = {
  page?: { title: string }
  workspace?: { title?: string; description?: string }
  resource?: {
    name?: string
    page?: string
    routes?: {
      create?: string | null
      detail?: string | null
      edit?: string | null
      index?: string | null
    }
  }
  actions?: ActionShape[]
  table?: {
    dataset?: {
      active_filters?: Record<string, TableFilterModelValue>
      columns?: TableColumnShape[]
      direction?: TableSortDirection | string
      filters?: TableFilterShape[]
      rows?: Array<{
        actions?: ActionShape[]
        id: string | number
        attributes: Record<string, unknown>
        links?: { detail?: string; edit?: string }
      }>
      routes?: {
        create?: string
      }
      search?: string
      sort?: string
      pagination?: {
        current_page?: number
        last_page?: number
        per_page?: number
        total?: number
      }
    }
  }
  form?: {
    layout?: FormContainerLayoutShape
    mode?: string
    schema?: FormNodeShape[]
    state?: Record<string, unknown>
    fields?: FormFieldShape[]
    sections?: FormGroupShape[]
    tabs?: FormGroupShape[]
    submit?: {
      method?: 'post' | 'put'
      url?: string
    }
    cancel?: {
      url?: string
    }
  } | null
  detail?: {
    entries?: DetailEntryShape[]
    sections?: DetailGroupShape[]
    relations?: Array<{ key?: string; label?: string; records?: Array<{ key: string | number; title: string }> }>
    routes?: {
      edit?: string | null
      index?: string | null
    }
  } | null
}

type TableSortDirection = 'asc' | 'desc'

const props = defineProps<{
  breadcrumbs?: Array<{ href: string; label: string }>
  payload: PayloadShape
}>()

const TABLE_INPUT_FILTER_AUTOSUBMIT_DELAY_MS = 1000
const OPERATION_TOAST_DURATION_MS = 2000
const form = useForm<Record<string, unknown>>({})
const toast = useToast()
const tableInputFilterTimers = new Map<string, ReturnType<typeof setTimeout>>()
const tableInputFilterValues = reactive<Record<string, string>>({})
const tableFilterPopoverOpen = reactive<Record<string, boolean>>({})
const tableFilterSearchTerms = reactive<Record<string, string>>({})
const rowActionConfirmationOpen = reactive<Record<string, boolean>>({})
const tableSearchValue = ref('')
let tableSearchTimer: ReturnType<typeof setTimeout> | null = null

const pagination = computed(() => props.payload.table?.dataset?.pagination)
const hasPagination = computed(() => (pagination.value?.last_page ?? 1) > 1)

const formSchema = computed(() => props.payload.form?.schema ?? props.payload.form?.fields ?? [])

const detailEntries = computed(() => props.payload.detail?.entries ?? [])
const detailEntryMap = computed(() => new Map(
  detailEntries.value.map((entry) => [entry.key, entry]),
))
const detailKeysInSections = computed(() => new Set(
  (props.payload.detail?.sections ?? []).flatMap((section) => (section.schema ?? []).map((entry) => entry.key)),
))
const standaloneDetailEntries = computed(() =>
  detailEntries.value.filter((entry) => !detailKeysInSections.value.has(entry.key)),
)
const visibleDetailSections = computed(() =>
  (props.payload.detail?.sections ?? [])
    .map((section) => ({
      ...section,
      schema: (section.schema ?? [])
        .map((entry) => detailEntryMap.value.get(entry.key))
        .filter((entry): entry is DetailEntryShape => Boolean(entry)),
    }))
    .filter((section) => (section.schema?.length ?? 0) > 0),
)
const tableDataset = computed(() => props.payload.table?.dataset)
const dataTableColumns = computed(() => tableDataset.value?.columns ?? [])
const searchableTableColumns = computed(() => dataTableColumns.value.filter((column) => column.searchable === true))
const hasSearchableTableColumns = computed(() => searchableTableColumns.value.length > 0)
const activeTableSearch = computed(() => String(tableDataset.value?.search ?? ''))
const activeTableSort = computed(() => String(tableDataset.value?.sort ?? ''))
const activeTableDirection = computed<TableSortDirection>(() => tableDataset.value?.direction === 'desc' ? 'desc' : 'asc')
const tableRows = computed(() =>
  (props.payload.table?.dataset?.rows ?? []).map((row) => ({
    _id: row.id,
    ...row.attributes,
    __actions: normalizeRowActions(row.actions, row.links),
    __links: row.links ?? {},
  })),
)
const hasTableRowActions = computed(() =>
  tableRows.value.some((row) => (row.__actions as ActionShape[]).length > 0),
)
const dataColumnWidth = computed(() =>
  hasTableRowActions.value
    ? `calc((100% - var(--fb-table-actions-width)) / ${Math.max(dataTableColumns.value.length, 1)})`
    : `calc(100% / ${Math.max(dataTableColumns.value.length, 1)})`,
)
const tableFilters = computed(() => tableDataset.value?.filters ?? [])
const hasTableFilters = computed(() => tableFilters.value.length > 0)
const hasActiveTableFilters = computed(() =>
  Object.values(tableDataset.value?.active_filters ?? {})
    .some((value) => tableFilterModelHasValue(value)),
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

watch(
  () => props.payload.form?.state,
  (state) => {
    form.defaults((state ?? {}) as Record<string, unknown>)
    form.reset()

    if (!state) {
      return
    }

    Object.assign(form, state)
  },
  { immediate: true },
)

onBeforeUnmount(() => {
  for (const timer of tableInputFilterTimers.values()) {
    clearTimeout(timer)
  }

  tableInputFilterTimers.clear()
  clearTableSearchTimer()
})

function rowActionControl(action: ActionShape) {
  if (action.requires_confirmation) {
    return rowActionConfirmationPopover(action)
  }

  return rowActionButton(action, () => runAction(action))
}

function rowActionButton(action: ActionShape, onClick?: () => void) {
  const UButton = resolveComponent('UButton')
  const label = rowActionLabel(action)
  const props: Record<string, unknown> = {
    'aria-label': label,
    color: action.color ?? 'neutral',
    icon: action.icon ?? fallbackRowActionIcon(action.key),
    size: 'xs',
    square: true,
    title: label,
    variant: 'ghost',
  }

  if (onClick !== undefined) {
    props.onClick = onClick
  }

  return h(UButton, props)
}

function rowActionConfirmationPopover(action: ActionShape) {
  const UPopover = resolveComponent('UPopover')
  const UButton = resolveComponent('UButton')
  const key = rowActionConfirmationKey(action)

  return h(UPopover, {
    key: `confirm-${key}`,
    content: { align: 'end', collisionPadding: 12, side: 'top', sideOffset: 8 },
    open: rowActionConfirmationOpen[key] ?? false,
    ui: { content: 'w-52 p-3' },
    'onUpdate:open': (open: boolean) => {
      rowActionConfirmationOpen[key] = open
    },
  }, {
    default: () => rowActionButton(action),
    content: () => h('div', { class: 'grid gap-3' }, [
      h('p', { class: 'm-0 text-sm font-medium leading-5 text-highlighted' }, rowActionConfirmationMessage(action)),
      h('div', { class: 'flex justify-end gap-2' }, [
        h(UButton, {
          color: 'neutral',
          label: 'Отмена',
          size: 'xs',
          variant: 'ghost',
          onClick: () => closeRowActionConfirmation(key),
        }),
        h(UButton, {
          color: action.color ?? 'error',
          label: rowActionConfirmationSubmitLabel(action),
          size: 'xs',
          variant: 'solid',
          onClick: () => {
            closeRowActionConfirmation(key)
            runAction(action, true)
          },
        }),
      ]),
    ]),
  })
}

function rowActionConfirmationKey(action: ActionShape): string {
  return `${action.method ?? 'post'}:${action.key}:${action.url ?? ''}`
}

function closeRowActionConfirmation(key: string): void {
  rowActionConfirmationOpen[key] = false
}

function rowActionConfirmationMessage(action: ActionShape): string {
  return action.key === 'delete' ? 'Удалить запись?' : 'Выполнить действие?'
}

function rowActionConfirmationSubmitLabel(action: ActionShape): string {
  return action.key === 'delete' ? 'Удалить' : 'Продолжить'
}

function rowActionLabel(action: ActionShape): string {
  return action.label ?? action.key
}

function fallbackRowActionIcon(actionKey: string): string {
  if (actionKey === 'view') {
    return 'i-lucide-eye'
  }

  if (actionKey === 'edit') {
    return 'i-lucide-pencil'
  }

  if (actionKey === 'delete') {
    return 'i-lucide-trash-2'
  }

  return 'i-lucide-circle-dot'
}

function normalizeRowActions(
  actions: ActionShape[] | undefined,
  links?: { detail?: string; edit?: string },
): ActionShape[] {
  if (actions === undefined) {
    return fallbackRowActions(links)
  }

  const visibleActions = actions.filter((action) =>
    action.visible !== false
    && typeof action.key === 'string'
    && action.key !== ''
    && typeof action.url === 'string'
    && action.url !== '',
  )

  return visibleActions
}

function fallbackRowActions(links?: { detail?: string; edit?: string }): ActionShape[] {
  return [
    links?.detail
      ? {
          icon: 'i-lucide-eye',
          key: 'view',
          label: 'View',
          method: 'get',
          url: links.detail,
        }
      : null,
    links?.edit
      ? {
          icon: 'i-lucide-pencil',
          key: 'edit',
          label: 'Edit',
          method: 'get',
          url: links.edit,
        }
      : null,
  ].filter((action): action is ActionShape => action !== null)
}

function renderTableValue(column: TableColumnShape, value: unknown) {
  if (column.type === 'badge') {
    const UBadge = resolveComponent('UBadge')

    return h(UBadge, {
      color: 'neutral',
      variant: 'subtle',
    }, () => formatValue(value))
  }

  return formatValue(value)
}

function tableCellTitle(value: unknown): string | undefined {
  const title = formatValue(value)

  return title === '' ? undefined : title
}

function sortDirectionForColumn(column: TableColumnShape): TableSortDirection | null {
  if (column.sortable !== true || activeTableSort.value !== column.key) {
    return null
  }

  return activeTableDirection.value
}

function sortableHeaderIcon(column: TableColumnShape): string {
  const direction = sortDirectionForColumn(column)

  if (direction === 'asc') {
    return 'i-lucide-arrow-up'
  }

  if (direction === 'desc') {
    return 'i-lucide-arrow-down'
  }

  return 'i-lucide-arrow-up-down'
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

function sortableHeaderLabel(column: TableColumnShape): string {
  const nextDirection = nextSortDirectionForColumn(column)

  if (nextDirection === 'asc') {
    return `Sort ${column.label} ascending`
  }

  if (nextDirection === 'desc') {
    return `Sort ${column.label} descending`
  }

  return `Clear ${column.label} sorting`
}

function renderTableHeader(column: TableColumnShape) {
  if (column.sortable !== true) {
    return column.label
  }

  const UButton = resolveComponent('UButton')
  const direction = sortDirectionForColumn(column)

  return h(UButton, {
    'aria-label': sortableHeaderLabel(column),
    active: direction !== null,
    class: {
      'table-sort-button': true,
      'table-sort-button--active': direction !== null,
    },
    color: direction === null ? 'neutral' : 'primary',
    icon: sortableHeaderIcon(column),
    label: column.label,
    size: 'xs',
    title: sortableHeaderLabel(column),
    trailing: true,
    variant: 'ghost',
    onClick: () => toggleTableSort(column),
  })
}

const tableColumns = computed(() => {
  const columns: Array<Record<string, unknown>> = dataTableColumns.value.map((column) => ({
      accessorKey: column.key,
      enableSorting: false,
      header: () => renderTableHeader(column),
      meta: {
        style: {
          th: { width: dataColumnWidth.value },
          td: { width: dataColumnWidth.value },
        },
      },
      cell: ({ row }: { row: { original: Record<string, unknown> } }) => {
        const value = row.original[column.key]

        return h('span', {
          class: 'table-cell-value',
          title: tableCellTitle(value),
        }, renderTableValue(column, value))
      },
    }))

  if (hasTableRowActions.value) {
    columns.push({
      id: '__actions',
      header: 'Actions',
      meta: {
        class: {
          th: 'fb-table-actions-cell',
          td: 'fb-table-actions-cell',
        },
        style: {
          th: { textAlign: 'right', width: 'var(--fb-table-actions-width)' },
          td: { textAlign: 'right', width: 'var(--fb-table-actions-width)' },
        },
      },
      cell: ({ row }: { row: { original: Record<string, unknown> } }) => {
        const actions = row.original.__actions as ActionShape[] | undefined

        return h('div', { class: 'row-actions' }, (actions ?? []).map((action) => rowActionControl(action)))
      },
    })
  }

  return columns
})

function visit(href?: string) {
  if (!href) {
    return
  }

  router.get(href)
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

  if (!tableFilterModelHasValue(value)) {
    return null
  }

  return value
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

function updateFieldValue(fieldKey: string, value: unknown) {
  form[fieldKey] = value
}

function submitForm() {
  const submit = props.payload.form?.submit

  if (!submit?.url) {
    return
  }

  if (submit.method === 'put') {
    form.put(submit.url, {
      onError: () => showOperationFailureToast('The form could not be saved. Please check the highlighted fields.'),
    })
    return
  }

  form.post(submit.url, {
    onError: () => showOperationFailureToast('The form could not be saved. Please check the highlighted fields.'),
  })
}

function showOperationFailureToast(message: string) {
  toast.add({
    title: message,
    color: 'error',
    icon: 'i-lucide-circle-x',
    duration: OPERATION_TOAST_DURATION_MS,
    type: 'foreground',
  })
}

function runAction(action: ActionShape, confirmed = false) {
  if (!action.url) {
    return
  }

  if (action.requires_confirmation && !confirmed && !window.confirm('Are you sure you want to continue?')) {
    return
  }

  const method = (action.method ?? 'post').toLowerCase()

  if (method === 'get') {
    router.get(action.url, {}, { preserveScroll: true })
    return
  }

  if (method === 'put') {
    router.put(action.url, {}, { preserveScroll: true })
    return
  }

  if (method === 'patch') {
    router.patch(action.url, {}, { preserveScroll: true })
    return
  }

  if (method === 'delete') {
    router.delete(action.url, { preserveScroll: true })
    return
  }

  router.post(action.url, {}, { preserveScroll: true })
}

function formatValue(value: unknown): string {
  if (value === null || value === undefined || value === '') {
    return '—'
  }

  if (typeof value === 'boolean') {
    return value ? 'Yes' : 'No'
  }

  if (Array.isArray(value)) {
    return value.map((item) => formatValue(item)).join(', ')
  }

  if (typeof value === 'object') {
    return JSON.stringify(value)
  }

  return String(value)
}
</script>

<template>
  <div v-if="breadcrumbs?.length" class="screen-breadcrumbs">
    <UBreadcrumb :items="breadcrumbs" />
  </div>

  <template v-if="payload.resource?.page === 'index'">
    <UCard variant="outline">
      <template #header>
        <div class="section-header">
          <div>
            <p class="section-kicker">Collection</p>
            <h3 class="section-title">{{ payload.resource?.name ?? 'Resource' }}</h3>
          </div>

          <div class="section-meta">
            <UButton
              v-if="hasActiveTableFilters"
              color="neutral"
              icon="i-lucide-x"
              size="xs"
              variant="ghost"
              @click="resetTableFilters"
            >
              Reset filters
            </UButton>

            <UBadge
              v-if="pagination?.total !== undefined"
              color="neutral"
              variant="subtle"
            >
              {{ pagination.total }} total
            </UBadge>
          </div>
        </div>
      </template>

      <div v-if="hasSearchableTableColumns || hasTableFilters" class="table-toolbar">
        <UFormField
          v-if="hasSearchableTableColumns"
          class="table-search-field"
          label="Search"
          name="search"
        >
          <UInput
            autocomplete="off"
            class="w-full"
            enterkeyhint="search"
            icon="i-lucide-search"
            :model-value="tableSearchValue"
            placeholder="Search"
            type="search"
            @blur="applyTableSearch"
            @keyup.enter="applyTableSearch"
            @update:model-value="updateTableSearchDraft"
          >
            <template v-if="hasActiveTableSearch || tableSearchValue !== ''" #trailing>
              <UButton
                aria-label="Clear search"
                color="neutral"
                icon="i-lucide-x"
                size="xs"
                square
                title="Clear search"
                variant="ghost"
                @click.stop.prevent="clearTableSearch"
              />
            </template>
          </UInput>
        </UFormField>

        <UFormField
          v-for="filter in tableFilters"
          :key="filter.key"
          :label="filter.label ?? filter.key"
          :name="`filters.${filter.key}`"
          class="table-filter-field"
        >
          <UInput
            v-if="filter.type === 'input'"
            autocomplete="off"
            class="w-full"
            enterkeyhint="search"
            :model-value="tableInputFilterValues[filter.key] ?? ''"
            :placeholder="filter.label ?? filter.key"
            type="search"
            @blur="applyTableInputFilter(filter.key)"
            @keyup.enter="applyTableInputFilter(filter.key)"
            @update:model-value="updateInputFilterDraft(filter.key, $event)"
          />

          <DatePickerFilter
            v-else-if="filter.type === 'date'"
            :filter="filter"
            :model-value="activeTableFilterScalarValue(filter.key)"
            @update:model-value="updateTableFilter(filter.key, $event)"
          />

          <LazySelectFilter
            v-else-if="filter.type === 'select' && filter.lazy === true"
            :filter="filter"
            :model-value="activeTableFilterValue(filter.key)"
            @update:model-value="updateTableFilter(filter.key, $event)"
          />

          <USelect
            v-else-if="filter.type === 'select' && filter.searchable !== true"
            class="w-full"
            :items="normalizeTableFilterOptions(filter)"
            :model-value="activeTableFilterValue(filter.key)"
            :multiple="filter.multiple === true"
            :placeholder="filter.label ?? filter.key"
            @update:model-value="updateTableFilter(filter.key, $event)"
          />

          <UPopover
            v-else-if="filter.type === 'select'"
            v-model:open="tableFilterPopoverOpen[filter.key]"
            :content="{ align: 'start', sideOffset: 8, collisionPadding: 12 }"
          >
            <UButton
              class="table-filter-trigger"
              color="neutral"
              trailing-icon="i-lucide-chevron-down"
              variant="outline"
            >
              {{ selectedTableFilterLabel(filter) ?? filter.label ?? filter.key }}
            </UButton>

            <template #content>
              <div class="table-filter-popover">
                <UInput
                  v-model="tableFilterSearchTerms[filter.key]"
                  autocomplete="off"
                  autofocus
                  icon="i-lucide-search"
                  placeholder="Search"
                />

                <div class="table-filter-options">
                  <UButton
                    v-for="option in searchableTableFilterOptions(filter)"
                    :key="String(option.value ?? option.label ?? '')"
                    :class="tableFilterOptionClass(filter, option)"
                    color="neutral"
                    type="button"
                    :variant="isTableFilterOptionSelected(filter, option.value) ? 'soft' : 'ghost'"
                    @click="selectTableFilterOption(filter, option.value)"
                  >
                    {{ option.label ?? option.value }}
                  </UButton>

                  <p v-if="searchableTableFilterOptions(filter).length === 0" class="table-filter-empty">
                    No data
                  </p>
                </div>
              </div>
            </template>
          </UPopover>

          <UInput
            v-else
            class="w-full"
            :model-value="activeTableFilterScalarValue(filter.key)"
            :placeholder="filter.label ?? filter.key"
            @update:model-value="updateTableFilter(filter.key, $event)"
          />
        </UFormField>
      </div>

      <UTable
        class="resource-table"
        :data="tableRows"
        :columns="tableColumns"
        :ui="{ base: 'w-full table-fixed' }"
        empty="The resource index route is wired, but there are no registered records to render yet."
      />

      <div v-if="payload.actions?.length || hasPagination" class="table-footer">
        <div v-if="payload.actions?.length" class="action-row">
          <UButton
            v-for="action in payload.actions"
            :key="action.key"
            color="neutral"
            variant="soft"
            @click="runAction(action)"
          >
            {{ action.label ?? action.key }}
          </UButton>
        </div>

        <div v-if="hasPagination && pagination" class="pagination-shell">
          <p class="pagination-summary">
            Page {{ pagination.current_page ?? 1 }} of {{ pagination.last_page ?? 1 }}
          </p>

          <UPagination
            :page="pagination.current_page ?? 1"
            :items-per-page="pagination.per_page ?? 15"
            :total="pagination.total ?? 0"
            active-color="primary"
            active-variant="subtle"
            color="neutral"
            show-edges
            size="sm"
            variant="ghost"
            @update:page="visitPage"
          >
            <template #prev>
              <span>Prev</span>
            </template>
            <template #next>
              <span>Next</span>
            </template>
          </UPagination>
        </div>
      </div>
    </UCard>
  </template>

  <template v-else-if="payload.page">
    <UCard variant="outline">
      <template #header>
        {{ payload.workspace?.title ?? payload.page.title }}
      </template>

      <p v-if="payload.workspace?.description">
        {{ payload.workspace.description }}
      </p>
      <p v-else>
        Custom page payload resolved through Inertia.
      </p>

      <template v-if="payload.workspace?.title || payload.actions?.length" #footer>
        <div class="action-row">
          <UButton
            v-for="action in payload.actions ?? []"
            :key="action.key"
            color="neutral"
            variant="soft"
            @click="runAction(action)"
          >
            {{ action.label ?? action.key }}
          </UButton>
        </div>
      </template>
    </UCard>
  </template>

  <template v-else-if="payload.resource?.page === 'create' || payload.resource?.page === 'edit'">
    <SimpleFormShell
      :cancel-url="payload.form?.cancel?.url"
      :errors="form.errors as Record<string, string>"
      :layout="payload.form?.layout"
      :mode="payload.form?.mode"
      :processing="form.processing"
      :resource-name="payload.resource?.name"
      :schema="formSchema"
      :state="form"
      @submit="submitForm"
      @update:field="updateFieldValue"
      @visit="visit"
    />
  </template>

  <template v-else-if="payload.resource?.page === 'detail'">
    <UCard variant="outline">
      <template #header>
        <div class="section-header">
          <div>
            <p class="section-kicker">Details</p>
            <h3 class="section-title">{{ payload.resource?.name ?? 'Resource' }}</h3>
          </div>
        </div>
      </template>

      <p class="section-description">
        Detail payload prepared with {{ payload.detail?.entries?.length ?? 0 }} entries
        and {{ payload.detail?.relations?.length ?? 0 }} relation groups.
      </p>

      <div v-if="visibleDetailSections.length" class="relations-stack">
        <UCard
          v-for="section in visibleDetailSections"
          :key="section.key"
          variant="soft"
        >
          <template #header>
            <div>
              {{ section.label ?? section.key }}
              <p v-if="section.description" class="subsection-description">
                {{ section.description }}
              </p>
            </div>
          </template>

          <div class="detail-stack">
            <div
              v-for="entry in section.schema ?? []"
              :key="entry.key"
              class="detail-row"
            >
              <div>
                <span class="detail-label">{{ entry.label ?? entry.key }}</span>
                <p v-if="entry.help" class="detail-help">
                  {{ entry.help }}
                </p>
              </div>
              <span class="detail-value">{{ formatValue(entry.value) }}</span>
            </div>
          </div>
        </UCard>
      </div>

      <div v-if="standaloneDetailEntries.length" class="detail-stack">
        <div
          v-for="entry in standaloneDetailEntries"
          :key="entry.key"
          class="detail-row"
        >
          <div>
            <span class="detail-label">{{ entry.label ?? entry.key }}</span>
            <p v-if="entry.help" class="detail-help">
              {{ entry.help }}
            </p>
          </div>
          <span class="detail-value">{{ formatValue(entry.value) }}</span>
        </div>
      </div>

      <div v-if="payload.detail?.relations?.length" class="relations-stack">
        <UCard
          v-for="relation in payload.detail.relations"
          :key="relation.key"
          variant="soft"
        >
          <template #header>
            {{ relation.label ?? relation.key }}
          </template>

          <div v-if="relation.records?.length" class="badge-row">
            <UBadge
              v-for="record in relation.records"
              :key="`${relation.key}-${record.key}`"
              color="neutral"
              variant="subtle"
            >
              {{ record.title }}
            </UBadge>
          </div>
          <UAlert
            v-else
            color="neutral"
            variant="subtle"
            description="No related records available yet."
          />
        </UCard>
      </div>

      <template #footer>
        <div class="action-row">
          <UButton
            v-if="payload.detail?.routes?.edit"
            color="primary"
            variant="outline"
            @click="visit(payload.detail.routes.edit)"
          >
            Edit
          </UButton>

          <UButton
            v-for="action in payload.actions ?? []"
            :key="action.key"
            :color="action.requires_confirmation ? 'warning' : 'neutral'"
            variant="soft"
            @click="runAction(action)"
          >
            {{ action.label ?? action.key }}
          </UButton>
        </div>
      </template>
    </UCard>
  </template>
</template>

<style scoped>
.action-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.screen-breadcrumbs {
  margin-bottom: 0.875rem;
  display: flex;
  justify-content: flex-start;
}

.form-page-shell {
  margin-inline: auto;
  max-width: 64rem;
}

.form-card {
  width: 100%;
  padding: 1.5rem;
}

.row-actions {
  display: flex;
  gap: 0.5rem;
  justify-content: flex-end;
}

.resource-table {
  --fb-table-actions-width: 7rem;
}

:deep(.table-cell-value) {
  display: block;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

:deep(.table-sort-button) {
  max-width: 100%;
  min-width: 0;
  padding-inline: 0;
  justify-content: flex-start;
}

:deep(.table-sort-button span) {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
}

.table-toolbar {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
  gap: 1rem;
  padding: 1rem 0;
}

.table-search-field,
.table-filter-field {
  min-width: 0;
}

.table-filter-trigger {
  width: 100%;
  justify-content: space-between;
}

.table-filter-popover {
  display: grid;
  gap: 0.5rem;
  width: min(18rem, calc(100vw - 2rem));
  padding: 0.5rem;
}

.table-filter-options {
  display: grid;
  gap: 0.125rem;
  max-height: 14rem;
  overflow-y: auto;
}

.table-filter-option {
  width: 100%;
  justify-content: flex-start;
}

.table-filter-empty {
  margin: 0;
  padding: 1rem 0.75rem;
  color: var(--ui-text-muted);
  font-size: 0.875rem;
  text-align: center;
}

.section-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
}

.section-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: 0.5rem;
}

.section-kicker {
  margin: 0 0 0.25rem;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: color-mix(in srgb, var(--ui-text-muted) 92%, transparent);
}

.section-title {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 700;
}

.section-description,
.subsection-description,
.detail-help {
  margin: 0.35rem 0 0;
  color: color-mix(in srgb, var(--ui-text-muted) 92%, transparent);
}

.table-footer {
  display: grid;
  gap: 1rem;
  padding-top: 1rem;
}

.pagination-shell {
  display: grid;
  justify-items: center;
  gap: 0.75rem;
}

.pagination-summary {
  margin: 0;
  font-size: 0.875rem;
  color: color-mix(in srgb, var(--ui-text-muted) 92%, transparent);
}

.detail-stack {
  display: grid;
  gap: 0.75rem;
  margin-top: 1rem;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid rgba(120, 96, 67, 0.12);
}

.detail-label {
  font-weight: 600;
  color: #6b5844;
}

.detail-value {
  text-align: right;
}

.relations-stack {
  display: grid;
  gap: 1rem;
  margin-top: 1rem;
}

.badge-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

</style>
