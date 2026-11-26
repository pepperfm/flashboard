<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { fetchRelationPayload } from './relationRequests'
import { appendTimelineRecords } from './timelineRecords'
import type { RelationManagerPayload } from './types'

const props = defineProps<{ relation: RelationManagerPayload }>()

const manager = ref(props.relation)
const loading = ref(false)
const failed = ref(false)
let generation = 0

watch(() => props.relation, (relation) => {
  generation++
  manager.value = relation
  loading.value = false
  failed.value = false
})
onBeforeUnmount(() => { generation++ })

const items = computed(() => (manager.value.records ?? []).map(record => ({
  value: String(record.key),
  title: record.title,
  description: record.description ?? undefined,
  date: record.date ?? undefined,
  icon: 'i-lucide-history',
  url: record.links?.detail ?? null,
})))

async function loadMore() {
  const next = manager.value.pagination?.next_page
  const endpoint = manager.value.records_url
  if (!next || !endpoint || loading.value) {
    return
  }

  const request = ++generation
  loading.value = true
  failed.value = false

  try {
    const url = new URL(endpoint, window.location.origin)
    url.searchParams.set('page', String(next))
    const payload = await fetchRelationPayload(url.toString())
    if (request === generation) {
      manager.value = {
        ...payload,
        records: appendTimelineRecords(manager.value.records ?? [], payload.records ?? []),
      }
    }
  } catch {
    if (request === generation) {
      failed.value = true
    }
  } finally {
    if (request === generation) {
      loading.value = false
    }
  }
}
</script>

<template>
  <UCard variant="outline" class="min-w-0">
    <template #header>
      <h3 class="font-semibold text-highlighted">{{ manager.label ?? manager.key }}</h3>
    </template>

    <UTimeline v-if="items.length" :items="items" :ui="{ wrapper: 'min-w-0', title: 'break-words', description: 'whitespace-pre-wrap break-words' }">
      <template #title="{ item }">
        <UButton
          v-if="item.url"
          :label="item.title"
          color="neutral"
          variant="link"
          class="p-0 text-start whitespace-normal"
          @click="router.get(item.url, {}, { preserveScroll: true })"
        />
        <span v-else>{{ item.title }}</span>
      </template>
    </UTimeline>
    <p v-else class="text-sm text-muted">{{ manager.empty_state?.description ?? 'No related records are available yet.' }}</p>

    <div v-if="manager.pagination?.has_more || failed" class="mt-4 space-y-2">
      <UAlert v-if="failed" color="error" variant="subtle" description="Could not load more records." />
      <UButton
        :label="failed ? 'Retry' : 'Load more'"
        icon="i-lucide-list-plus"
        color="neutral"
        variant="ghost"
        :loading="loading"
        @click="loadMore"
      />
    </div>
  </UCard>
</template>
