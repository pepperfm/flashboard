<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useToast } from '@nuxt/ui/composables'
import { parseJsonEntry } from './jsonEntryValue'

const props = defineProps<{ value: unknown }>()
const open = ref(false)
const toast = useToast()
const entry = computed(() => parseJsonEntry(props.value))

watch(() => props.value, () => { open.value = false })

async function copy() {
  if (entry.value.status !== 'ok' || entry.value.text === null) {
    return
  }

  try {
    await navigator.clipboard.writeText(entry.value.text)
    toast.add({ title: 'JSON copied', color: 'success' })
  } catch {
    toast.add({ title: 'Could not copy JSON', color: 'error' })
  }
}
</script>

<template>
  <div class="w-full min-w-0 space-y-2">
    <div class="flex flex-wrap items-center gap-2">
      <UButton
        v-if="entry.text !== null"
        :icon="open ? 'i-lucide-chevron-down' : 'i-lucide-chevron-right'"
        :label="open ? 'Hide JSON' : 'Show JSON'"
        color="neutral"
        variant="outline"
        size="sm"
        :aria-expanded="open"
        @click="open = !open"
      />
      <UButton
        v-if="entry.status === 'ok' && entry.text !== null"
        icon="i-lucide-copy"
        label="Copy"
        color="neutral"
        variant="ghost"
        size="sm"
        @click="copy"
      />
      <UBadge v-if="entry.status === 'truncated'" color="warning" variant="subtle">
        Truncated
      </UBadge>
      <span v-if="entry.status === 'invalid'" class="text-sm text-muted">Invalid or unsupported JSON</span>
      <span v-else-if="entry.text === null" class="text-sm text-muted">JSON exceeds display limits</span>
    </div>
    <pre v-if="open && entry.text !== null" class="max-h-96 overflow-auto rounded-md border border-default bg-elevated p-3 text-xs whitespace-pre-wrap break-all text-default"><code>{{ entry.text }}</code></pre>
  </div>
</template>
