<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import { useToast } from '@nuxt/ui/composables'
import FBActionDialog from '@/components/flashboard/FBActionDialog.vue'
import FBCustomPageRenderer from '@/components/flashboard/custom-pages/FBCustomPageRenderer.vue'
import FBJsonEntry from '@/components/flashboard/detail/FBJsonEntry.vue'
import FBMetrics from '@/components/flashboard/metrics/FBMetrics.vue'
import type { MetricsPayload } from '@/components/flashboard/metrics/types'
import FBTimeline from '@/components/flashboard/relations/FBTimeline.vue'
import FBHasManyRelationManager from '@/components/flashboard/relations/FBHasManyRelationManager.vue'
import FBHasOneRelationManager from '@/components/flashboard/relations/FBHasOneRelationManager.vue'
import SimpleFormShell from '@/components/flashboard/forms/layout/SimpleFormShell.vue'
import DatePickerFilter from '@/components/flashboard/table/DatePickerFilter.vue'
import DateRangePickerFilter from '@/components/flashboard/table/DateRangePickerFilter.vue'
import type { TableColumnShape, TableStateDataset, TableSortDirection } from '@/components/flashboard/table/types'
import { useTableState } from '@/components/flashboard/table/useTableState'
import LazySelectFilter from '@/components/flashboard/table/LazySelectFilter.vue'
import type { FormContainerLayoutShape } from '@/components/flashboard/forms/layout/resolveFormLayout'
import type { FormFieldShape, FormNodeShape } from '@/components/flashboard/forms/renderers/resolveFormFieldRenderer'
import type { RelationPayloadShape } from '@/components/flashboard/relations/types'
import { isRelationManagerPayload } from '@/components/flashboard/relations/types'
import { computed, h, reactive, resolveComponent, watch } from 'vue'
import { useScreenActions, type ActionShape } from '@/components/flashboard/useScreenActions'

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

type PageComponentAssetShape = {
  url: string
  type?: string
}

type PageComponentPayloadShape = {
  assets?: {
    scripts?: PageComponentAssetShape[]
    styles?: PageComponentAssetShape[]
  }
  name: string
  props?: Record<string, unknown>
}

type PayloadShape = {
  metrics?: MetricsPayload | null
  metadata?: Record<string, unknown>
  page?: { key?: string; title: string; type?: string; uri?: string }
  workspace?: { title?: string; description?: string }
  component?: PageComponentPayloadShape | null
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
    dataset?: TableStateDataset & {
      rows?: Array<{
        actions?: ActionShape[]
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
    relations?: RelationPayloadShape[]
    routes?: {
      edit?: string | null
      index?: string | null
    }
  } | null
  relations?: RelationPayloadShape[]
}

const props = defineProps<{
  payload: PayloadShape
}>()

const OPERATION_TOAST_DURATION_MS = 2000
// The form schema is supplied by the backend at runtime; field values are heterogeneous.
const form = useForm<Record<string, any>>({})
const toast = useToast()
const { activeAction, runAction } = useScreenActions()
const rowActionConfirmationOpen = reactive<Record<string, boolean>>({})
const pagination = computed(() => props.payload.table?.dataset?.pagination)
const hasPagination = computed(() => (pagination.value?.last_page ?? 1) > 1)

const formSchema = computed(() => props.payload.form?.schema ?? props.payload.form?.fields ?? [])
const formFields = computed(() => props.payload.form?.fields ?? flattenFormFields(formSchema.value))

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
const detailRelations = computed(() => props.payload.detail?.relations ?? [])
const legacyDetailRelations = computed(() =>
  detailRelations.value.filter((relation) => !isRelationManagerPayload(relation)),
)
const detailRelationManagers = computed(() =>
  detailRelations.value.filter(isRelationManagerPayload),
)
const screenRelationManagers = computed(() =>
  (props.payload.relations ?? []).filter(isRelationManagerPayload),
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
const {
  dataTableColumns, hasSearchableTableColumns, tableFilters, hasTableFilters,
  hasActiveTableFilters, hasActiveTableSearch, tableInputFilterValues,
  tableFilterPopoverOpen, tableFilterSearchTerms, tableSearchValue,
  sortDirectionForColumn, nextSortDirectionForColumn, toggleTableSort, visitPage,
  activeTableFilterValue, activeDateRange, updateDateRange, activeTableFilterScalarValue,
  updateInputFilterDraft, applyTableInputFilter, updateTableSearchDraft,
  applyTableSearch, clearTableSearch, normalizeTableFilterOptions,
  searchableTableFilterOptions, selectedTableFilterLabel, selectTableFilterOption,
  tableFilterOptionClass, updateTableFilter, isTableFilterOptionSelected, resetTableFilters,
} = useTableState(tableDataset)
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

function rowActionControl(action: ActionShape) {
  if (action.form_url || (action.scope && action.scope !== "legacy" && action.requires_confirmation)) {
    return rowActionButton(action, () => runAction(action))
  }
  if (action.requires_confirmation) {
    return rowActionConfirmationPopover(action)
  }

  return rowActionButton(action, () => runAction(action))
}

function rowActionButton(action: ActionShape, onClick?: () => void) {
  const UButton = resolveComponent('UButton')
  const UTooltip = resolveComponent('UTooltip')
  const label = rowActionLabel(action)
  const props: Record<string, unknown> = {
    'aria-label': label,
    color: action.color ?? 'neutral',
    icon: action.icon ?? fallbackRowActionIcon(action.key),
    size: 'xs',
    square: true,
    variant: 'ghost',
  }

  if (onClick !== undefined) {
    props.onClick = onClick
  }

  return h(UTooltip, { text: label }, { default: () => h(UButton, props) })
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
  const actions: Array<ActionShape | null> = [
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
  ]

  return actions.filter((action): action is ActionShape => action !== null)
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

  return h(resolveComponent('UTooltip'), { text: sortableHeaderLabel(column) }, {
    default: () => h(UButton, {
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
      trailing: true,
      variant: 'ghost',
      onClick: () => toggleTableSort(column),
    }),
  })
}

const tableColumns = computed(() => {
  const columns: Array<Record<string, unknown>> = dataTableColumns.value.map((column) => ({
      id: column.key,
      accessorFn: (row: Record<string, unknown>) => row[column.key],
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

        const cell = () => h('span', {
          class: 'table-cell-value',
        }, renderTableValue(column, value))
        const title = tableCellTitle(value)

        return title
          ? h(resolveComponent('UTooltip'), { text: title, ui: { content: 'h-auto max-w-[min(32rem,90vw)]', text: 'whitespace-normal break-words' } }, { default: cell })
          : cell()
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

function updateFieldValue(fieldKey: string, value: unknown) {
  form[fieldKey] = value
}

function submitForm() {
  const submit = props.payload.form?.submit

  if (!submit?.url) {
    return
  }

  const hasSelectedFiles = formHasSelectedFiles()
  const submitOptions = {
    forceFormData: hasSelectedFiles,
    onError: () => showOperationFailureToast('The form could not be saved. Please check the highlighted fields.'),
  }

  if (submit.method === 'put') {
    if (hasSelectedFiles) {
      form
        .transform((data) => ({
          ...data,
          _method: 'put',
        }))
        .post(submit.url, {
          ...submitOptions,
          onFinish: () => resetFormTransform(),
        })

      return
    }

    resetFormTransform()
    form.put(submit.url, submitOptions)
    return
  }

  resetFormTransform()
  form.post(submit.url, submitOptions)
}

function resetFormTransform() {
  form.transform((data) => data)
}

function formHasSelectedFiles(): boolean {
  return formFields.value
    .filter((field) => isFileUploadField(field))
    .some((field) => containsFileValue(form[field.key]))
}

function isFileUploadField(field: FormFieldShape): boolean {
  return field.renderer === 'file_upload' || field.type === 'file' || field.input_type === 'file'
}

function containsFileValue(value: unknown): boolean {
  if (typeof File !== 'undefined' && value instanceof File) {
    return true
  }

  if (!Array.isArray(value)) {
    return false
  }

  return value.some((item) => containsFileValue(item))
}

function flattenFormFields(nodes: FormNodeShape[]): FormFieldShape[] {
  const fields: FormFieldShape[] = []

  for (const node of nodes) {
    if (isFormFieldNode(node)) {
      fields.push(node)
      continue
    }

    if (node.kind === 'tabs') {
      fields.push(...flattenFormFields(node.tabs ?? []))
      continue
    }

    fields.push(...flattenFormFields(node.schema ?? []))
  }

  return fields
}

function isFormFieldNode(node: FormNodeShape): node is FormFieldShape {
  return !node.kind || node.kind === 'field'
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
  <FBActionDialog :action="activeAction" @close="activeAction = null" />
  <template v-if="payload.resource?.page === 'index'">
    <FBMetrics v-if="payload.metrics?.items?.length" :metrics="payload.metrics" />
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
              <UTooltip text="Clear search">
                <UButton
                  aria-label="Clear search"
                  color="neutral"
                  icon="i-lucide-x"
                  size="xs"
                  square
                  variant="ghost"
                  @click.stop.prevent="clearTableSearch"
                />
              </UTooltip>
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

          <DateRangePickerFilter
            v-else-if="filter.type === 'date_range'"
            :label="filter.label ?? filter.key"
            :model-value="activeDateRange(filter.key)"
            @update:model-value="updateDateRange(filter.key, $event)"
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

  <template v-else-if="payload.page?.key === 'dashboard'">
    <FBMetrics v-if="payload.metrics?.items?.length" :metrics="payload.metrics" />
    <UCard v-else variant="outline">
      <template #header>Dashboard</template>
      Define metrics in your Flashboard Resources to see analytics here.
    </UCard>
  </template>

  <template v-else-if="payload.page && payload.component?.name">
    <FBCustomPageRenderer
      :actions="payload.actions ?? []"
      :component="payload.component"
      :metadata="payload.metadata"
      :page="payload.page"
      :workspace="payload.workspace"
    />
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

  <div
    v-else-if="payload.resource?.page === 'create' || payload.resource?.page === 'edit'"
    class="resource-form-screen"
  >
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
    <div
      v-if="screenRelationManagers.length"
      class="relation-manager-stack"
    >
      <template
        v-for="relation in screenRelationManagers"
        :key="relation.key"
      >
        <FBTimeline
          v-if="relation.type === 'has_many' && relation.presentation === 'timeline'"
          :relation="relation"
        />
        <FBHasOneRelationManager
          v-else-if="relation.type === 'has_one'"
          :relation="relation"
        />
        <FBHasManyRelationManager
          v-else-if="relation.type === 'has_many'"
          :relation="relation"
        />
      </template>
    </div>
  </div>

  <div
    v-else-if="payload.resource?.page === 'detail'"
    class="resource-detail-screen"
  >
    <UCard variant="outline">
      <template #header>
        <div class="section-header">
          <div>
            <p class="section-kicker">Details</p>
            <h3 class="section-title">{{ payload.resource?.name ?? 'Resource' }}</h3>
          </div>
        </div>
      </template>

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
              :class="{ 'detail-row-json': entry.type === 'json' }"
            >
              <div>
                <span class="detail-label">{{ entry.label ?? entry.key }}</span>
                <p v-if="entry.help" class="detail-help">
                  {{ entry.help }}
                </p>
              </div>
              <FBJsonEntry v-if="entry.type === 'json'" :value="entry.value" />
              <span v-else class="detail-value">{{ formatValue(entry.value) }}</span>
            </div>
          </div>
        </UCard>
      </div>

      <div v-if="standaloneDetailEntries.length" class="detail-stack">
        <div
          v-for="entry in standaloneDetailEntries"
          :key="entry.key"
          class="detail-row"
          :class="{ 'detail-row-json': entry.type === 'json' }"
        >
          <div>
            <span class="detail-label">{{ entry.label ?? entry.key }}</span>
            <p v-if="entry.help" class="detail-help">
              {{ entry.help }}
            </p>
          </div>
          <FBJsonEntry v-if="entry.type === 'json'" :value="entry.value" />
          <span v-else class="detail-value">{{ formatValue(entry.value) }}</span>
        </div>
      </div>

      <div v-if="legacyDetailRelations.length" class="relations-stack">
        <UCard
          v-for="relation in legacyDetailRelations"
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
    <div
      v-if="detailRelationManagers.length"
      class="relation-manager-stack"
    >
      <template
        v-for="relation in detailRelationManagers"
        :key="relation.key"
      >
        <FBTimeline
          v-if="relation.type === 'has_many' && relation.presentation === 'timeline'"
          :relation="relation"
        />
        <FBHasOneRelationManager
          v-else-if="relation.type === 'has_one'"
          :relation="relation"
        />
        <FBHasManyRelationManager
          v-else-if="relation.type === 'has_many'"
          :relation="relation"
        />
      </template>
    </div>
  </div>
</template>

<style scoped>
.action-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.form-page-shell {
  margin-inline: auto;
  max-width: 64rem;
}

.form-card {
  width: 100%;
  padding: 1.5rem;
}

.resource-form-screen,
.resource-detail-screen,
.relation-manager-stack {
  display: grid;
  gap: 1rem;
}

.relation-manager-stack {
  margin-top: 1rem;
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
  border-bottom: 1px solid var(--ui-border);
}

.detail-row-json {
  flex-direction: column;
}

.detail-label {
  font-weight: 600;
  color: var(--ui-text-toned);
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
