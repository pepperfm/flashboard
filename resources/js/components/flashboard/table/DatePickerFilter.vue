<script setup lang="ts">
import { parseDate, type CalendarDate, type DateValue } from '@internationalized/date'
import { computed, ref, watch } from 'vue'

type TableFilterOptionValue = string | number | boolean

type DateTableFilterShape = {
  key: string
  label?: string
}

type InputDateSegmentExpose = {
  $el?: HTMLElement | null
}

type InputDateExpose = {
  inputsRef?: Array<HTMLElement | InputDateSegmentExpose | null | undefined>
}

const props = defineProps<{
  filter: DateTableFilterShape
  modelValue: TableFilterOptionValue | null | undefined
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string | null]
}>()

const ISO_DATE_PATTERN = /^\d{4}-\d{2}-\d{2}$/

const inputDate = ref<InputDateExpose | null>(null)
const isOpen = ref(false)
const selectedDate = ref<CalendarDate | null>(null)

const popoverReference = computed(() => {
  const segment = inputDate.value?.inputsRef?.[3]

  if (!segment) {
    return undefined
  }

  if (typeof HTMLElement !== 'undefined' && segment instanceof HTMLElement) {
    return segment
  }

  return segment.$el ?? undefined
})

watch(
  () => props.modelValue,
  (value) => {
    selectedDate.value = parseModelValue(value)
  },
  { immediate: true },
)

function parseModelValue(value: TableFilterOptionValue | null | undefined): CalendarDate | null {
  if (typeof value !== 'string' || !ISO_DATE_PATTERN.test(value)) {
    return null
  }

  try {
    return parseDate(value)
  } catch {
    return null
  }
}

function updateDate(value: DateValue | null | undefined) {
  selectedDate.value = value ? parseDate(serializeDate(value)) : null
  emit('update:modelValue', value ? serializeDate(value) : null)

  if (value) {
    isOpen.value = false
  }
}

function serializeDate(value: DateValue): string {
  return [
    String(value.year).padStart(4, '0'),
    String(value.month).padStart(2, '0'),
    String(value.day).padStart(2, '0'),
  ].join('-')
}
</script>

<template>
  <UInputDate
    ref="inputDate"
    class="w-full"
    color="neutral"
    :model-value="selectedDate"
    :name="filter.key"
    @update:model-value="updateDate"
  >
    <template #trailing>
      <UPopover
        v-model:open="isOpen"
        :content="{ align: 'start', sideOffset: 8, collisionPadding: 12 }"
        :reference="popoverReference"
      >
        <UButton
          aria-label="Open calendar"
          color="neutral"
          icon="i-lucide-calendar"
          size="xs"
          square
          type="button"
          variant="ghost"
        />

        <template #content>
          <UCalendar
            class="p-2"
            color="neutral"
            :model-value="selectedDate"
            @update:model-value="updateDate"
          />
        </template>
      </UPopover>
    </template>
  </UInputDate>
</template>
