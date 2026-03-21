<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import { computed, h, ref, resolveComponent, watch } from 'vue'

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

type FormOptionShape = {
  label?: string
  value?: string | number | boolean
}

type FormFieldShape = {
  key: string
  label?: string
  type?: string
  input_type?: string
  placeholder?: string
  hint?: string
  help?: string
  required?: boolean
  options?: FormOptionShape[] | Record<string, string | number | boolean>
}

type FormGroupShape = {
  key: string
  label?: string
  description?: string
  icon?: string
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
    mode?: string
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
const activeTab = ref<string | number>('')

const pagination = computed(() => props.payload.table?.dataset?.pagination)
const hasPagination = computed(() => (pagination.value?.last_page ?? 1) > 1)

const allowedFormFields = computed(() => props.payload.form?.fields ?? [])
const allowedFormFieldMap = computed(() => new Map(
  allowedFormFields.value.map((field) => [field.key, field]),
))
const formFieldKeysInGroups = computed(() => new Set(
  [...(props.payload.form?.sections ?? []), ...(props.payload.form?.tabs ?? [])].flatMap(
    (group) => (group.schema ?? []).map((field) => field.key),
  ),
))
const standaloneFormFields = computed(() =>
  allowedFormFields.value.filter((field) => !formFieldKeysInGroups.value.has(field.key)),
)
const visibleFormSections = computed(() =>
  (props.payload.form?.sections ?? [])
    .map((section) => ({
      ...section,
      schema: (section.schema ?? [])
        .map((field) => allowedFormFieldMap.value.get(field.key))
        .filter((field): field is FormFieldShape => Boolean(field)),
    }))
    .filter((section) => (section.schema?.length ?? 0) > 0),
)
const visibleFormTabs = computed(() =>
  (props.payload.form?.tabs ?? [])
    .map((tab) => ({
      ...tab,
      schema: (tab.schema ?? [])
        .map((field) => allowedFormFieldMap.value.get(field.key))
        .filter((field): field is FormFieldShape => Boolean(field)),
    }))
    .filter((tab) => (tab.schema?.length ?? 0) > 0),
)
const tabItems = computed(() =>
  visibleFormTabs.value.map((tab) => ({
    label: tab.label ?? tab.key,
    icon: tab.icon,
    value: tab.key,
  })),
)
const activeTabSchema = computed(() =>
  visibleFormTabs.value.find((tab) => tab.key === activeTab.value)?.schema ?? [],
)
const hasStructuredFormLayout = computed(() =>
  visibleFormSections.value.length > 0 || visibleFormTabs.value.length > 0,
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

watch(
  () => tabItems.value,
  (items) => {
    const hasActive = items.some((item) => item.value === activeTab.value)

    if (!hasActive) {
      activeTab.value = items[0]?.value ?? ''
    }
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

function fieldComponent(field: FormFieldShape): 'UInput' | 'UTextarea' | 'USelect' | 'USwitch' {
  if (field.type === 'select') {
    return 'USelect'
  }

  if (field.type === 'toggle') {
    return 'USwitch'
  }

  if (/(description|notes|content|body)/i.test(field.key)) {
    return 'UTextarea'
  }

  return 'UInput'
}

function isToggleField(field: FormFieldShape): boolean {
  return fieldComponent(field) === 'USwitch'
}

function fieldModelValue(fieldKey: string): unknown {
  return form[fieldKey]
}

function updateFieldValue(fieldKey: string, value: unknown) {
  form[fieldKey] = value
}

function fieldError(fieldKey?: string): string | undefined {
  if (!fieldKey) {
    return undefined
  }

  const error = form.errors[fieldKey]

  return typeof error === 'string' ? error : undefined
}

function normalizeSelectItems(field: FormFieldShape): FormOptionShape[] {
  const options = field.options ?? []

  if (Array.isArray(options)) {
    return options
  }

  return Object.entries(options).map(([value, label]) => ({
    label: String(label),
    value,
  }))
}

function fieldComponentProps(field: FormFieldShape): Record<string, unknown> {
  const placeholder = field.placeholder ?? field.label ?? field.key
  const component = fieldComponent(field)

  if (component === 'USelect') {
    return {
      items: normalizeSelectItems(field),
      name: field.key,
      placeholder,
      required: field.required,
    }
  }

  if (component === 'USwitch') {
    return {
      description: field.help ?? field.hint,
      label: field.label ?? field.key,
      name: field.key,
    }
  }

  if (component === 'UTextarea') {
    return {
      autoresize: true,
      name: field.key,
      placeholder,
      rows: 4,
    }
  }

  return {
    name: field.key,
    placeholder,
    required: field.required,
    type: field.input_type ?? 'text',
  }
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
    <div class="form-page-shell">
      <UPageCard class="form-card">
        <template #header>
          <div class="section-header">
            <div>
              <p class="section-kicker">{{ payload.form?.mode === 'edit' ? 'Edit' : 'Create' }}</p>
              <h3 class="section-title">{{ payload.resource?.name ?? 'Resource' }}</h3>
            </div>
          </div>
        </template>

        <UForm :state="form" class="form-stack" @submit.prevent="submitForm">
          <UTabs
            v-if="tabItems.length"
            v-model="activeTab"
            :items="tabItems"
            :content="false"
            color="neutral"
            variant="pill"
            class="tabs-shell"
          />

          <div v-if="activeTabSchema.length" class="section-stack">
            <UCard
              v-for="field in activeTabSchema"
              :key="field.key"
              variant="soft"
            >
              <UFormField
                v-if="!isToggleField(field)"
                :name="field.key"
                :label="field.label ?? field.key"
                :hint="field.hint"
                :help="field.help"
                :error="fieldError(field.key)"
                :required="field.required"
              >
                <component
                  :is="fieldComponent(field)"
                  :model-value="fieldModelValue(field.key)"
                  v-bind="fieldComponentProps(field)"
                  @update:model-value="updateFieldValue(field.key, $event)"
                />
              </UFormField>

              <UFormField
                v-else
                :name="field.key"
                :error="fieldError(field.key)"
              >
                <component
                  :is="fieldComponent(field)"
                  :model-value="fieldModelValue(field.key)"
                  v-bind="fieldComponentProps(field)"
                  @update:model-value="updateFieldValue(field.key, $event)"
                />
              </UFormField>
            </UCard>
          </div>

          <div
            v-for="section in visibleFormSections"
            :key="section.key"
            class="section-stack"
          >
            <UCard variant="soft">
              <template #header>
                <div>
                  <h4 class="subsection-title">{{ section.label ?? section.key }}</h4>
                  <p v-if="section.description" class="subsection-description">
                    {{ section.description }}
                  </p>
                </div>
              </template>

              <div class="field-grid">
                <div
                  v-for="field in section.schema ?? []"
                  :key="field.key"
                >
                  <UFormField
                    v-if="!isToggleField(field)"
                    :name="field.key"
                    :label="field.label ?? field.key"
                    :hint="field.hint"
                    :help="field.help"
                    :error="fieldError(field.key)"
                    :required="field.required"
                  >
                    <component
                      :is="fieldComponent(field)"
                      :model-value="fieldModelValue(field.key)"
                      v-bind="fieldComponentProps(field)"
                      @update:model-value="updateFieldValue(field.key, $event)"
                    />
                  </UFormField>

                  <UFormField
                    v-else
                    :name="field.key"
                    :error="fieldError(field.key)"
                  >
                    <component
                      :is="fieldComponent(field)"
                      :model-value="fieldModelValue(field.key)"
                      v-bind="fieldComponentProps(field)"
                      @update:model-value="updateFieldValue(field.key, $event)"
                    />
                  </UFormField>
                </div>
              </div>
            </UCard>
          </div>

          <div
            v-if="standaloneFormFields.length"
            :class="hasStructuredFormLayout ? 'field-grid' : 'simple-field-stack'"
          >
            <div
              v-for="field in standaloneFormFields"
              :key="field.key"
              :class="{ 'simple-field-row': !hasStructuredFormLayout }"
            >
              <UFormField
                v-if="!isToggleField(field)"
                :name="field.key"
                :label="field.label ?? field.key"
                :hint="field.hint"
                :help="field.help"
                :error="fieldError(field.key)"
                :required="field.required"
              >
                <component
                  :is="fieldComponent(field)"
                  :model-value="fieldModelValue(field.key)"
                  v-bind="fieldComponentProps(field)"
                  @update:model-value="updateFieldValue(field.key, $event)"
                />
              </UFormField>

              <UFormField
                v-else
                :name="field.key"
                :error="fieldError(field.key)"
              >
                <component
                  :is="fieldComponent(field)"
                  :model-value="fieldModelValue(field.key)"
                  v-bind="fieldComponentProps(field)"
                  @update:model-value="updateFieldValue(field.key, $event)"
                />
              </UFormField>
            </div>
          </div>
        </UForm>

        <template #footer>
          <div class="action-row">
            <UButton color="primary" :loading="form.processing" @click="submitForm">
              {{ payload.form?.mode === 'edit' ? 'Save changes' : 'Create record' }}
            </UButton>

            <UButton
              v-if="payload.form?.cancel?.url"
              color="neutral"
              variant="ghost"
              @click="visit(payload.form.cancel.url)"
            >
              Cancel
            </UButton>
          </div>
        </template>
      </UPageCard>
    </div>
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

.form-stack {
  display: grid;
  gap: 1rem;
}

.tabs-shell {
  margin-bottom: 0.5rem;
}

.field-grid {
  display: grid;
  gap: 1rem;
}

.simple-field-stack {
  display: grid;
  gap: 1.25rem;
}

.simple-field-row {
  display: grid;
  gap: 0.5rem;
}

.section-stack {
  display: grid;
  gap: 1rem;
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

.subsection-title {
  margin: 0;
  font-size: 1rem;
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

@media (min-width: 720px) {
  .field-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
