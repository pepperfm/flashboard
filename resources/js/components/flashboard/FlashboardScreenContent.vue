<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import { computed, h, resolveComponent, watch } from 'vue'

type PayloadShape = {
  page?: { title: string }
  workspace?: { title?: string; description?: string }
  resource?: {
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
  payload: PayloadShape
}>()

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
        const UButton = resolveComponent('UButton')
        const links = row.original.__links as { detail?: string; edit?: string } | undefined

        return h('div', { class: 'row-actions' }, [
          links?.detail
            ? h(UButton, {
              color: 'neutral',
              variant: 'ghost',
              onClick: () => visit(links.detail),
            }, { default: () => 'Open' })
            : null,
          links?.edit
            ? h(UButton, {
              color: 'neutral',
              variant: 'ghost',
              onClick: () => visit(links.edit),
            }, { default: () => 'Edit' })
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

  router.visit(action.url, {
    method: (action.method ?? 'post') as 'post',
    preserveScroll: true,
  })
}
</script>

<template>
  <template v-if="payload.resource?.page === 'index'">
    <UCard variant="outline">
      <template #header>
        Resource Index
      </template>

      <UTable
        :data="tableRows"
        :columns="tableColumns"
        empty="The resource index route is wired, but there are no registered records to render yet."
      />

      <template v-if="payload.actions?.length" #footer>
        <div class="action-row">
          <UButton
            v-if="payload.table?.dataset?.routes?.create"
            color="primary"
            @click="visit(payload.table.dataset.routes.create)"
          >
            Create record
          </UButton>

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
      </template>
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
    <UCard variant="outline">
      <template #header>
        {{ payload.form?.mode ?? 'create' }} form
      </template>

      <p>
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
          <UButton color="neutral" variant="outline" @click="visit(payload.form?.cancel?.url)">
            Cancel
          </UButton>
        </div>
      </template>
    </UCard>
  </template>

  <template v-else-if="payload.resource?.page === 'detail'">
    <UCard variant="outline">
      <template #header>
        Detail screen
      </template>

      <p>
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
            v-if="payload.detail?.routes?.index"
            color="neutral"
            variant="ghost"
            @click="visit(payload.detail.routes.index)"
          >
            Back to list
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

.row-actions {
  display: flex;
  gap: 0.5rem;
}

.form-stack {
  display: grid;
  gap: 1rem;
  margin-top: 1rem;
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
