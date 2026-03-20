<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import { computed, h, resolveComponent, watch } from 'vue'

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
  actions?: Array<{
    key: string
    label?: string
    method?: string
    requires_confirmation?: boolean
    success_message?: string | null
    url?: string
  }>
  table?: {
    dataset?: {
      columns?: Array<{ key: string; label: string }>
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
    fields?: Array<{ key?: string; label?: string }>
    submit?: {
      method?: 'post' | 'put'
      url?: string
    }
    cancel?: {
      url?: string
    }
  } | null
  detail?: {
    entries?: Array<{ label?: string; value?: unknown }>
    relations?: Array<{ key?: string; label?: string; records?: Array<{ key: string | number; title: string }> }>
    routes?: {
      edit?: string | null
      index?: string | null
    }
  } | null
}

const props = defineProps<{
  breadcrumbs?: Array<{ href: string; label: string }>
  payload: PayloadShape
}>()

type RowActionConfig = {
  ariaLabel: string
  href: string
  icon: string
}

const pagination = computed(() => props.payload.table?.dataset?.pagination)
const hasPagination = computed(() => (pagination.value?.last_page ?? 1) > 1)

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

const tableColumns = computed(() =>
  [
    ...(props.payload.table?.dataset?.columns ?? []).map((column) => ({
      accessorKey: column.key,
      header: column.label,
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
const form = useForm<Record<string, unknown>>({})

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

function fieldComponent(key?: string) {
  if (!key) {
    return 'UInput'
  }

  return /(description|notes|content|body)/i.test(key) ? 'UTextarea' : 'UInput'
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

function runAction(action: { url?: string; method?: string; requires_confirmation?: boolean }) {
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
      <UCard class="form-card" variant="outline">
        <template #header>
          <div class="section-header">
            <div>
              <p class="section-kicker">{{ payload.form?.mode === 'edit' ? 'Edit' : 'Create' }}</p>
              <h3 class="section-title">{{ payload.resource?.name ?? 'Resource' }}</h3>
            </div>
          </div>
        </template>

        <p class="section-description">
          Form payload prepared with {{ payload.form?.fields?.length ?? 0 }} field definitions.
        </p>

        <div class="form-stack">
          <UFormField
            v-for="field in payload.form?.fields ?? []"
            :key="field.key ?? field.label"
            :name="field.key"
            :label="field.label ?? field.key"
          >
            <component
              :is="fieldComponent(field.key)"
              v-model="form[field.key ?? '']"
              :name="field.key"
              :placeholder="field.label ?? field.key"
              :rows="fieldComponent(field.key) === 'UTextarea' ? 4 : undefined"
            />
          </UFormField>
        </div>

        <template #footer>
          <div class="action-row">
            <UButton color="primary" :loading="form.processing" @click="submitForm">
              {{ payload.form?.mode === 'edit' ? 'Save changes' : 'Create record' }}
            </UButton>
          </div>
        </template>
      </UCard>
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

      <div class="detail-stack">
        <div
          v-for="entry in payload.detail?.entries ?? []"
          :key="entry.label"
          class="detail-row"
        >
          <span class="detail-label">{{ entry.label }}</span>
          <span class="detail-value">{{ entry.value ?? '—' }}</span>
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

          <div class="badge-row" v-if="relation.records?.length">
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
}

.row-actions {
  display: flex;
  gap: 0.5rem;
  justify-content: flex-end;
}

.form-stack {
  display: grid;
  gap: 1rem;
  margin-top: 1rem;
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

.section-description {
  margin: 0 0 1rem;
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
