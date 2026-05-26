<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  description?: string
  disabled?: boolean
  label?: string
  modelValue?: boolean | null
  name?: string
  required?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean | null | undefined]
}>()

const modelValue = computed(() => props.modelValue ?? false)
</script>

<template>
  <UCheckbox
    :description="props.description"
    :disabled="props.disabled"
    :label="props.label"
    :model-value="modelValue"
    :name="props.name"
    :required="props.required"
    @update:model-value="emit('update:modelValue', $event === 'indeterminate' ? null : $event)"
  />
</template>
