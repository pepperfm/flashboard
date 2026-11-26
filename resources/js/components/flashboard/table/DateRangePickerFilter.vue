<script setup lang="ts">
import { parseDate, type DateValue } from '@internationalized/date'
import { computed, ref, shallowRef, watch } from 'vue'
import type { DateRangeValue } from './dateRangeFilter'

const props = defineProps<{
  label: string
  modelValue: DateRangeValue | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: DateRangeValue | null]
}>()

const open = ref(false)
const draft = shallowRef<{ start: DateValue, end: DateValue } | null>(null)
const display = computed(() => props.modelValue
  ? props.modelValue.start + ' – ' + props.modelValue.end
  : props.label)

watch(open, (value) => {
  if (value) {
    draft.value = props.modelValue
      ? { start: parseDate(props.modelValue.start), end: parseDate(props.modelValue.end) }
      : null
  }
})

function apply() {
  if (!draft.value?.start || !draft.value?.end) {
    return
  }

  emit('update:modelValue', {
    start: draft.value.start.toString(),
    end: draft.value.end.toString(),
  })
  open.value = false
}

function clear() {
  draft.value = null
  emit('update:modelValue', null)
  open.value = false
}
</script>

<template>
  <UPopover v-model:open="open" :content="{ align: 'start', collisionPadding: 12 }">
    <UButton
      block
      color="neutral"
      variant="outline"
      icon="i-lucide-calendar-range"
      type="button"
      :label="display"
    />

    <template #content>
      <div class="p-3 space-y-3">
        <UCalendar v-model="draft" range />
        <div class="flex justify-between gap-2">
          <UButton color="neutral" variant="ghost" label="Clear" type="button" @click="clear" />
          <UButton label="Apply" type="button" :disabled="!draft?.start || !draft?.end" @click="apply" />
        </div>
      </div>
    </template>
  </UPopover>
</template>
