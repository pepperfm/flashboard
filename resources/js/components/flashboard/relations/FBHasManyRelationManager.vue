<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import RelationConfirmButton from '@/components/flashboard/relations/RelationConfirmButton.vue'
import { fetchRelationPayload } from '@/components/flashboard/relations/relationRequests'
import type { RelationActionShape, RelationManagerPayload, RelationRecordShape, RelationScalarValue } from '@/components/flashboard/relations/types'
import { hasRelationValue } from '@/components/flashboard/relations/types'
import { useRelationOptions } from '@/components/flashboard/relations/useRelationOptions'

const props = defineProps<{
  relation: RelationManagerPayload
}>()

const manager = ref<RelationManagerPayload>(props.relation)
const attachValue = ref<RelationScalarValue | null>(null)
const selectedKeys = ref<RelationScalarValue[]>([])
const syncValues = ref<RelationScalarValue[]>(initialRecordKeys(props.relation))
const isRefreshing = ref(false)
const refreshFailed = ref(false)
const isMutating = ref(false)

watch(
  () => props.relation,
  (relation) => {
    manager.value = relation
    attachValue.value = null
    selectedKeys.value = []
    syncValues.value = initialRecordKeys(relation)
  },
)

const attachOptions = useRelationOptions(
  computed(() => manager.value.options_url),
  computed(() => manager.value.per_page),
  attachValue,
)
const syncOptions = useRelationOptions(
  computed(() => manager.value.options_url),
  computed(() => manager.value.per_page),
  syncValues,
)

const records = computed(() => manager.value.records ?? manager.value.selected_records ?? [])
const visibleActions = computed(() => (manager.value.actions ?? []).filter((action) => action.visible !== false && action.url))
const createAction = computed(() => actionByKey('create'))
const attachAction = computed(() => actionByKey('attach'))
const detachAction = computed(() => actionByKey('detach'))
const syncAction = computed(() => actionByKey('sync'))
const canAttach = computed(() => !manager.value.read_only && attachAction.value !== undefined)
const canDetach = computed(() => !manager.value.read_only && detachAction.value !== undefined)
const canSync = computed(() => !manager.value.read_only && syncAction.value !== undefined)
const pagination = computed(() => manager.value.pagination)

function actionByKey(key: string): RelationActionShape | undefined {
  return visibleActions.value.find((action) => action.key === key)
}

function visitAction(action: RelationActionShape | undefined) {
  if (!action?.url) {
    return
  }

  router.get(action.url, {}, { preserveScroll: true })
}

function submitAttach() {
  if (!attachAction.value?.url || !hasRelationValue(attachValue.value)) {
    return
  }

  runMutation(attachAction.value, { related: attachValue.value })
}

function detachSelected() {
  if (!detachAction.value?.url || selectedKeys.value.length === 0) {
    return
  }

  runMutation(detachAction.value, { related: selectedKeys.value })
}

function detachRecord(record: RelationRecordShape) {
  if (!detachAction.value?.url) {
    return
  }

  runMutation(detachAction.value, { related: [record.key] })
}

function submitSync() {
  if (!syncAction.value?.url) {
    return
  }

  runMutation(syncAction.value, { related: syncValues.value })
}

function runMutation(action: RelationActionShape, data: Record<string, unknown>) {
  if (!action.url) {
    return
  }

  isMutating.value = true
  router.visit(action.url, {
    data,
    method: (action.method ?? 'post').toLowerCase(),
    preserveScroll: true,
    onFinish: () => {
      isMutating.value = false
    },
  })
}

async function refreshRecords(page = 1, append = false) {
  if (typeof window === 'undefined' || !manager.value.records_url || isRefreshing.value) {
    return
  }

  isRefreshing.value = true
  refreshFailed.value = false

  try {
    const url = new URL(manager.value.records_url, window.location.origin)
    url.searchParams.set('page', String(page))
    const payload = await fetchRelationPayload(url.toString())

    manager.value = append
      ? {
          ...payload,
          records: [...records.value, ...(payload.records ?? [])],
          selected_records: [...records.value, ...(payload.selected_records ?? [])],
        }
      : payload
  } catch {
    refreshFailed.value = true
  } finally {
    isRefreshing.value = false
  }
}

function loadMoreRecords() {
  const nextPage = pagination.value?.next_page

  if (nextPage) {
    void refreshRecords(nextPage, true)
  }
}

function openRecord(record: RelationRecordShape, routeKey: 'detail' | 'edit') {
  const url = record.links?.[routeKey]

  if (url) {
    router.get(url, {}, { preserveScroll: true })
  }
}

function updateSelectedKey(record: RelationRecordShape, checked: boolean | 'indeterminate') {
  const key = record.key
  const nextKeys = selectedKeys.value.filter((selectedKey) => String(selectedKey) !== String(key))

  selectedKeys.value = checked === true ? [...nextKeys, key] : nextKeys
}

function recordIsSelected(record: RelationRecordShape): boolean {
  return selectedKeys.value.some((selectedKey) => String(selectedKey) === String(record.key))
}

function initialRecordKeys(relation: RelationManagerPayload): RelationScalarValue[] {
  return (relation.selected_records ?? relation.records ?? [])
    .map((record) => record.key)
    .filter(hasRelationValue)
}
</script>

<template>
  <section class="relation-manager">
    <header class="relation-manager__header">
      <div>
        <p class="relation-manager__kicker">Has many</p>
        <h3 class="relation-manager__title">
          {{ manager.label ?? manager.key }}
        </h3>
      </div>
      <div class="relation-manager__actions">
        <UBadge
          color="neutral"
          :label="String(records.length)"
          variant="subtle"
        />
        <UButton
          v-if="createAction"
          :aria-label="createAction.label ?? 'Create'"
          color="primary"
          :icon="createAction.icon ?? 'i-lucide-plus'"
          :label="createAction.label ?? 'Create'"
          size="xs"
          variant="soft"
          @click="visitAction(createAction)"
        />
        <UButton
          v-if="manager.records_url"
          aria-label="Refresh"
          color="neutral"
          icon="i-lucide-refresh-cw"
          :loading="isRefreshing"
          size="xs"
          square
          title="Refresh"
          variant="ghost"
          @click="refreshRecords()"
        />
      </div>
    </header>

    <UAlert
      v-if="refreshFailed"
      color="error"
      icon="i-lucide-circle-x"
      title="Could not load relation"
      variant="subtle"
    />

    <div v-if="records.length" class="relation-records">
      <div
        v-for="record in records"
        :key="`${manager.key}-${record.key}`"
        class="relation-record"
      >
        <UCheckbox
          v-if="canDetach"
          :aria-label="`Select ${record.title}`"
          :model-value="recordIsSelected(record)"
          size="sm"
          @update:model-value="updateSelectedKey(record, $event)"
        />
        <div class="relation-record__main">
          <span class="relation-record__title">{{ record.title }}</span>
          <span class="relation-record__key">{{ record.key }}</span>
        </div>
        <div class="relation-record__actions">
          <UButton
            v-if="record.links?.detail"
            aria-label="Open"
            color="neutral"
            icon="i-lucide-eye"
            size="xs"
            square
            title="Open"
            variant="ghost"
            @click="openRecord(record, 'detail')"
          />
          <UButton
            v-if="record.links?.edit"
            aria-label="Edit"
            color="neutral"
            icon="i-lucide-pencil"
            size="xs"
            square
            title="Edit"
            variant="ghost"
            @click="openRecord(record, 'edit')"
          />
          <RelationConfirmButton
            v-if="canDetach"
            color="warning"
            :disabled="isMutating"
            icon="i-lucide-unlink"
            label="Detach"
            message="Detach this record?"
            submit-label="Detach"
            @confirm="detachRecord(record)"
          />
        </div>
      </div>
    </div>

    <UAlert
      v-else
      color="neutral"
      :description="manager.empty_state?.description ?? 'No related records are selected.'"
      icon="i-lucide-list"
      :title="manager.empty_state?.title ?? 'No related records'"
      variant="subtle"
    />

    <div
      v-if="pagination?.has_more"
      class="relation-footer"
    >
      <UButton
        color="neutral"
        icon="i-lucide-list-plus"
        label="Load more"
        :loading="isRefreshing"
        size="xs"
        variant="ghost"
        @click="loadMoreRecords"
      />
    </div>

    <div
      v-if="canDetach && selectedKeys.length"
      class="relation-toolbar"
    >
      <RelationConfirmButton
        color="warning"
        :disabled="isMutating"
        icon="i-lucide-unlink"
        label="Detach selected"
        message="Detach selected records?"
        submit-label="Detach"
        @confirm="detachSelected"
      />
    </div>

    <div
      v-if="canAttach"
      class="relation-picker"
    >
      <USelectMenu
        v-model:open="attachOptions.isOpen"
        v-model:search-term="attachOptions.searchTerm"
        class="relation-picker__select"
        :disabled="isMutating"
        :ignore-filter="true"
        :items="attachOptions.items"
        :loading="attachOptions.isLoading"
        :model-value="attachValue"
        placeholder="Select record"
        :reset-search-term-on-blur="false"
        :search-input="{ autocomplete: 'off', icon: 'i-lucide-search', placeholder: 'Search' }"
        value-key="value"
        @update:model-value="attachValue = $event"
      >
        <template v-if="attachOptions.loadFailed" #empty>
          Could not load records.
        </template>
        <template v-if="attachOptions.hasMore" #content-bottom>
          <div class="relation-picker__more">
            <UButton
              block
              color="neutral"
              label="Load more"
              :loading="attachOptions.isLoading"
              size="xs"
              variant="ghost"
              @click.stop="attachOptions.loadMore"
            />
          </div>
        </template>
      </USelectMenu>
      <UButton
        :disabled="isMutating || !hasRelationValue(attachValue)"
        icon="i-lucide-link"
        label="Attach"
        :loading="isMutating"
        size="sm"
        variant="soft"
        @click="submitAttach"
      />
    </div>

    <div
      v-if="canSync"
      class="relation-picker"
    >
      <USelectMenu
        v-model:open="syncOptions.isOpen"
        v-model:search-term="syncOptions.searchTerm"
        class="relation-picker__select"
        :disabled="isMutating"
        :ignore-filter="true"
        :items="syncOptions.items"
        :loading="syncOptions.isLoading"
        :model-value="syncValues"
        multiple
        placeholder="Select records"
        :reset-search-term-on-blur="false"
        :search-input="{ autocomplete: 'off', icon: 'i-lucide-search', placeholder: 'Search' }"
        value-key="value"
        @update:model-value="syncValues = Array.isArray($event) ? $event : []"
      >
        <template v-if="syncOptions.loadFailed" #empty>
          Could not load records.
        </template>
        <template v-if="syncOptions.hasMore" #content-bottom>
          <div class="relation-picker__more">
            <UButton
              block
              color="neutral"
              label="Load more"
              :loading="syncOptions.isLoading"
              size="xs"
              variant="ghost"
              @click.stop="syncOptions.loadMore"
            />
          </div>
        </template>
      </USelectMenu>
      <RelationConfirmButton
        color="warning"
        :disabled="isMutating"
        icon="i-lucide-list-checks"
        label="Sync"
        message="Sync selected records?"
        submit-label="Sync"
        @confirm="submitSync"
      />
    </div>
  </section>
</template>

<style scoped>
.relation-manager {
  display: grid;
  gap: 0.875rem;
  padding: 1rem;
  border: 1px solid color-mix(in srgb, var(--ui-border) 82%, transparent);
  border-radius: 0.5rem;
  background: var(--ui-bg);
}

.relation-manager__header,
.relation-record,
.relation-picker,
.relation-toolbar,
.relation-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
}

.relation-manager__kicker {
  margin: 0 0 0.25rem;
  color: var(--ui-text-muted);
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
}

.relation-manager__title {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
}

.relation-manager__actions,
.relation-record__actions {
  display: flex;
  align-items: center;
  gap: 0.375rem;
}

.relation-records {
  display: grid;
  gap: 0.5rem;
}

.relation-record {
  min-height: 2.75rem;
  padding: 0.625rem 0;
  border-bottom: 1px solid color-mix(in srgb, var(--ui-border) 70%, transparent);
}

.relation-record:last-child {
  border-bottom: 0;
}

.relation-record__main {
  min-width: 0;
  flex: 1;
  display: grid;
  gap: 0.125rem;
}

.relation-record__title,
.relation-record__key {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.relation-record__title {
  font-weight: 600;
}

.relation-record__key {
  color: var(--ui-text-muted);
  font-size: 0.8rem;
}

.relation-picker__select {
  min-width: 14rem;
  flex: 1;
}

.relation-picker__more {
  padding: 0.375rem;
}

@media (max-width: 640px) {
  .relation-manager__header,
  .relation-picker,
  .relation-toolbar,
  .relation-footer {
    align-items: stretch;
    flex-direction: column;
  }

  .relation-picker__select {
    width: 100%;
  }
}
</style>
