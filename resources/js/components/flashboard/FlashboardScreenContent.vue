<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import SimpleFormShell from '@/components/flashboard/forms/layout/SimpleFormShell.vue'
import type { FormContainerLayoutShape } from '@/components/flashboard/forms/layout/resolveFormLayout'
import type { FormFieldShape, FormNodeShape } from '@/components/flashboard/forms/renderers/resolveFormFieldRenderer'
import { computed, h, resolveComponent, watch } from 'vue'

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
      columns?: TableColumnShape[]
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

const pagination = computed(() => props.payload.table?.dataset?.pagination)
const hasPagination = computed(() => (pagination.value?.last_page ?? 1) > 1)

const allowedFormFields = computed(() => props.payload.form?.fields ?? [])
const formSchema = computed(() => props.payload.form?.schema ?? allowedFormFields.value)

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
    ...(props.payload.table?.dataset?.columns ?? []).map((column) => ({
      accessorKey: column.key,
      header: column.label,
      cell: ({ row }: { row: { original: Record<string, unknown> } }) =>
        renderTableValue(column, row.original[column.key]),
    })),
    {
      id: '__actions',
      header: 'Actions',
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

function updateFieldValue(fieldKey: string, value: unknown) {
  form[fieldKey] = value
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

          <UBadge
            v-if="pagination?.total !== undefined"
            color="neutral"
            variant="subtle"
          >
            {{ pagination.total }} total
          </UBadge>
        </div>
      </template>

      <UTable
        :data="tableRows"
        :columns="tableColumns"
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

.section-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
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
