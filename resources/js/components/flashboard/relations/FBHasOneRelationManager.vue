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
const selectedValue = ref<RelationScalarValue | null>(null)
const isRefreshing = ref(false)
const refreshFailed = ref(false)
const isMutating = ref(false)

watch(
  () => props.relation,
  (relation) => {
    manager.value = relation
    selectedValue.value = null
  },
)

const options = useRelationOptions(
  computed(() => manager.value.options_url),
  computed(() => manager.value.per_page),
  selectedValue,
)

const selectedRecord = computed(() => manager.value.selected_record ?? manager.value.records?.[0] ?? null)
const visibleActions = computed(() => (manager.value.actions ?? []).filter((action) => action.visible !== false && action.url))
const createAction = computed(() => actionByKey('create'))
const attachAction = computed(() => actionByKey('attach'))
const detachAction = computed(() => actionByKey('detach'))
const replaceAction = computed(() => actionByKey('replace'))
const canAttach = computed(() => !manager.value.read_only && attachAction.value !== undefined && selectedRecord.value === null)
const canReplace = computed(() => !manager.value.read_only && replaceAction.value !== undefined && selectedRecord.value !== null)
const selectDisabled = computed(() => isMutating.value || (!canAttach.value && !canReplace.value))
const selectPlaceholder = computed(() => canReplace.value ? 'Select replacement' : 'Select record')

function actionByKey(key: string): RelationActionShape | undefined {
  return visibleActions.value.find((action) => action.key === key)
}

function visitAction(action: RelationActionShape | undefined) {
  if (!action?.url) {
    return
  }

  router.get(action.url, {}, { preserveScroll: true })
}

function submitSelection() {
  const action = canReplace.value ? replaceAction.value : attachAction.value

  if (!action?.url || !hasRelationValue(selectedValue.value)) {
    return
  }

  isMutating.value = true
  router.visit(action.url, {
    data: { related: selectedValue.value },
    method: (action.method ?? 'post').toLowerCase(),
    preserveScroll: true,
    onFinish: () => {
      isMutating.value = false
    },
  })
}

function detachRecord() {
  if (!detachAction.value?.url) {
    return
  }

  isMutating.value = true
  router.visit(detachAction.value.url, {
    data: selectedRecord.value ? { related: selectedRecord.value.key } : {},
    method: 'delete',
    preserveScroll: true,
    onFinish: () => {
      isMutating.value = false
    },
  })
}

async function refreshRecords() {
  if (typeof window === 'undefined' || !manager.value.records_url || isRefreshing.value) {
    return
  }

  isRefreshing.value = true
  refreshFailed.value = false

  try {
    manager.value = await fetchRelationPayload(manager.value.records_url)
  } catch {
    refreshFailed.value = true
  } finally {
    isRefreshing.value = false
  }
}

function openRecord(record: RelationRecordShape, routeKey: 'detail' | 'edit') {
  const url = record.links?.[routeKey]

  if (url) {
    router.get(url, {}, { preserveScroll: true })
  }
}
</script>

<template>
  <section class="relation-manager">
    <header class="relation-manager__header">
      <div>
        <p class="relation-manager__kicker">Has one</p>
        <h3 class="relation-manager__title">
          {{ manager.label ?? manager.key }}
        </h3>
      </div>
      <div class="relation-manager__actions">
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
          @click="refreshRecords"
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

    <div v-if="selectedRecord" class="relation-record">
      <div class="relation-record__main">
        <span class="relation-record__title">{{ selectedRecord.title }}</span>
        <span class="relation-record__key">{{ selectedRecord.key }}</span>
      </div>
      <div class="relation-record__actions">
        <UButton
          v-if="selectedRecord.links?.detail"
          aria-label="Open"
          color="neutral"
          icon="i-lucide-eye"
          size="xs"
          square
          title="Open"
          variant="ghost"
          @click="openRecord(selectedRecord, 'detail')"
        />
        <UButton
          v-if="selectedRecord.links?.edit"
          aria-label="Edit"
          color="neutral"
          icon="i-lucide-pencil"
          size="xs"
          square
          title="Edit"
          variant="ghost"
          @click="openRecord(selectedRecord, 'edit')"
        />
        <RelationConfirmButton
          v-if="detachAction"
          color="warning"
          :disabled="isMutating"
          icon="i-lucide-unlink"
          label="Detach"
          message="Detach this record?"
          submit-label="Detach"
          @confirm="detachRecord"
        />
      </div>
    </div>

    <UAlert
      v-else
      color="neutral"
      :description="manager.empty_state?.description ?? 'No related record is selected.'"
      icon="i-lucide-circle"
      :title="manager.empty_state?.title ?? 'No related record'"
      variant="subtle"
    />

    <div v-if="canAttach || canReplace" class="relation-picker">
      <USelectMenu
        v-model:open="options.isOpen"
        v-model:search-term="options.searchTerm"
        class="relation-picker__select"
        :disabled="selectDisabled"
        :ignore-filter="true"
        :items="options.items"
        :loading="options.isLoading"
        :model-value="selectedValue"
        :placeholder="selectPlaceholder"
        :reset-search-term-on-blur="false"
        :search-input="{ autocomplete: 'off', icon: 'i-lucide-search', placeholder: 'Search' }"
        value-key="value"
        @update:model-value="selectedValue = $event"
      >
        <template v-if="options.loadFailed" #empty>
          Could not load records.
        </template>
        <template v-if="options.hasMore" #content-bottom>
          <div class="relation-picker__more">
            <UButton
              block
              color="neutral"
              label="Load more"
              :loading="options.isLoading"
              size="xs"
              variant="ghost"
              @click.stop="options.loadMore"
            />
          </div>
        </template>
      </USelectMenu>

      <RelationConfirmButton
        v-if="canReplace"
        color="warning"
        :disabled="isMutating || !hasRelationValue(selectedValue)"
        icon="i-lucide-refresh-cw"
        label="Replace"
        message="Replace the related record?"
        submit-label="Replace"
        @confirm="submitSelection"
      />
      <UButton
        v-else
        :disabled="isMutating || !hasRelationValue(selectedValue)"
        icon="i-lucide-link"
        label="Attach"
        :loading="isMutating"
        size="sm"
        variant="soft"
        @click="submitSelection"
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
.relation-picker {
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

.relation-record__main {
  min-width: 0;
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
</style>
