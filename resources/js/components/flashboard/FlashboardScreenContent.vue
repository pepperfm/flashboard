<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import SimpleFormShell from '@/components/flashboard/forms/layout/SimpleFormShell.vue'
import LazySelectFilter from '@/components/flashboard/table/LazySelectFilter.vue'
import type { FormContainerLayoutShape } from '@/components/flashboard/forms/layout/resolveFormLayout'
import type { FormFieldShape, FormNodeShape } from '@/components/flashboard/forms/renderers/resolveFormFieldRenderer'
import { computed, h, reactive, resolveComponent, watch } from 'vue'

type ActionShape = {
  key: string
  label?: string
  method?: string
  requires_confirmation?: boolean
  success_message?: string | null
  url?: string
}

type TableColumnShape = {
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

type TableFilterShape = {
  key: string
  lazy?: boolean
  label?: string
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
      filters?: TableFilterShape[]
      rows?: Array<{
        id: string | number
        attributes: Record<string, unknown>
        links?: { detail?: string; edit?: string }
      }>
      routes?: {
        create?: string
      }
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

type RowActionConfig = {
  ariaLabel: string
  href: string
  icon: string
}

const props = defineProps<{
  breadcrumbs?: Array<{ href: string; label: string }>
  payload: PayloadShape
}>()

const form = useForm<Record<string, unknown>>({})
const tableFilterPopoverOpen = reactive<Record<string, boolean>>({})
const tableFilterSearchTerms = reactive<Record<string, string>>({})

const pagination = computed(() => props.payload.table?.dataset?.pagination)
const hasPagination = computed(() => (pagination.value?.last_page ?? 1) > 1)

const isCreateForm = computed(() => props.payload.resource?.page === 'create' || props.payload.form?.mode === 'create')
const allowedFormFields = computed(() => removeGeneratedPrimaryKeyFields(props.payload.form?.fields ?? []))
const formSchema = computed(() =>
  removeGeneratedPrimaryKeyNodes(props.payload.form?.schema ?? allowedFormFields.value),
)

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
const dataTableColumns = computed(() => props.payload.table?.dataset?.columns ?? [])
const dataColumnWidth = computed(() =>
  `calc((100% - var(--fb-table-actions-width)) / ${Math.max(dataTableColumns.value.length, 1)})`,
)
const tableFilters = computed(() => props.payload.table?.dataset?.filters ?? [])
const hasTableFilters = computed(() => tableFilters.value.length > 0)
const hasActiveTableFilters = computed(() =>
  Object.values(props.payload.table?.dataset?.active_filters ?? {})
    .some((value) => tableFilterModelHasValue(value)),
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

function rowActionButton(action: RowActionConfig) {
  const UButton = resolveComponent('UButton')

  return h(UButton, {
    'aria-label': action.ariaLabel,
    color: 'neutral',
    icon: action.icon,
    square: true,
    title: action.ariaLabel,
    variant: 'ghost',
    onClick: () => visit(action.href),
  })
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

const tableColumns = computed(() =>
  [
    ...dataTableColumns.value.map((column) => ({
      accessorKey: column.key,
      header: column.label,
      meta: {
        style: {
          th: { width: dataColumnWidth.value },
          td: { width: dataColumnWidth.value },
        },
      },
      cell: ({ row }: { row: { original: Record<string, unknown> } }) =>
        renderTableValue(column, row.original[column.key]),
    })),
    {
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
        const links = row.original.__links as { detail?: string; edit?: string } | undefined

        return h('div', { class: 'row-actions' }, [
          links?.detail
            ? rowActionButton({
              ariaLabel: 'Open record',
              href: links.detail,
              icon: 'i-lucide-eye',
            })
            : null,
          links?.edit
            ? rowActionButton({
              ariaLabel: 'Edit record',
              href: links.edit,
              icon: 'i-lucide-pencil',
            })
            : null,
        ])
      },
    },
  ],
)

const tableRows = computed(() =>
  (props.payload.table?.dataset?.rows ?? []).map((row) => ({
    _id: row.id,
    ...row.attributes,
    __links: row.links ?? {},
  })),
)

function visit(href?: string) {
  if (!href) {
    return
  }

  router.get(href)
}

function visitPage(page: number) {
  if (typeof window === 'undefined') {
    return
  }

  const url = new URL(window.location.href)

  if (page <= 1) {
    url.searchParams.delete('page')
  } else {
    url.searchParams.set('page', String(page))
  }

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

function activeTableFilterValue(filterKey: string): TableFilterModelValue {
  const value = props.payload.table?.dataset?.active_filters?.[filterKey]

  if (!tableFilterModelHasValue(value)) {
    return null
  }

  return value
}

function activeTableFilterScalarValue(filterKey: string): TableFilterOptionValue | null {
  return normalizeTableFilterModelValue(activeTableFilterValue(filterKey))[0] ?? null
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
  if (typeof window === 'undefined') {
    return
  }

  tableFilterSearchTerms[filterKey] = ''

  const url = new URL(window.location.href)

  deleteTableFilterQueryParams(url, filterKey)

  if (Array.isArray(value)) {
    for (const item of normalizeTableFilterModelValue(value)) {
      url.searchParams.append(`filters[${filterKey}][]`, String(item))
    }
  } else if (tableFilterScalarHasValue(value)) {
    url.searchParams.set(`filters[${filterKey}]`, String(value))
  }

  url.searchParams.delete('page')

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
  if (typeof window === 'undefined') {
    return
  }

  const url = new URL(window.location.href)

  Array.from(url.searchParams.keys())
    .filter((key) => key.startsWith('filters[') || key === 'filters')
    .forEach((key) => url.searchParams.delete(key))

  url.searchParams.delete('page')

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

function updateFieldValue(fieldKey: string, value: unknown) {
  form[fieldKey] = value
}

function shouldHideGeneratedPrimaryKeyField(field: FormFieldShape): boolean {
  return isCreateForm.value && field.key === 'id'
}

function removeGeneratedPrimaryKeyFields(fields: FormFieldShape[]): FormFieldShape[] {
  return fields.filter((field) => !shouldHideGeneratedPrimaryKeyField(field))
}

function removeGeneratedPrimaryKeyNodes(nodes: FormNodeShape[]): FormNodeShape[] {
  return nodes
    .map((node) => {
      if (node.kind === 'section') {
        return {
          ...node,
          schema: removeGeneratedPrimaryKeyNodes(node.schema ?? []),
        }
      }

      if (node.kind === 'tab') {
        return {
          ...node,
          schema: removeGeneratedPrimaryKeyNodes(node.schema ?? []),
        }
      }

      if (node.kind === 'tabs') {
        return {
          ...node,
          tabs: (node.tabs ?? [])
            .map((tab) => ({
              ...tab,
              schema: removeGeneratedPrimaryKeyNodes(tab.schema ?? []),
            }))
            .filter((tab) => (tab.schema ?? []).length > 0),
        }
      }

      return shouldHideGeneratedPrimaryKeyField(node) ? null : node
    })
    .filter((node): node is FormNodeShape => {
      if (node === null) {
        return false
      }

      if (node.kind === 'section' || node.kind === 'tab') {
        return (node.schema ?? []).length > 0
      }

      if (node.kind === 'tabs') {
        return (node.tabs ?? []).length > 0
      }

      return true
    })
}

function submitForm() {
  const submit = props.payload.form?.submit

  if (!submit?.url) {
    return
  }

  if (submit.method === 'put') {
    form.put(submit.url)
    return
  }

  form.post(submit.url)
}

function runAction(action: ActionShape) {
  if (!action.url) {
    return
  }

  if (action.requires_confirmation && !window.confirm('Are you sure you want to continue?')) {
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
            <UBadge
              v-if="pagination?.total !== undefined"
              color="neutral"
              variant="subtle"
            >
              {{ pagination.total }} total
            </UBadge>

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
          </div>
        </div>
      </template>

      <div v-if="hasTableFilters" class="table-toolbar">
        <UFormField
          v-for="filter in tableFilters"
          :key="filter.key"
          :label="filter.label ?? filter.key"
          :name="`filters.${filter.key}`"
          class="table-filter-field"
        >
          <LazySelectFilter
            v-if="filter.type === 'select' && filter.lazy === true"
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

.table-toolbar {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
  gap: 1rem;
  padding: 1rem 0;
}

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
  display: grid;
  justify-items: end;
  gap: 0.35rem;
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
