<script setup lang="ts">
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
</script>

<template>
  <USwitch
    :description="props.description"
    :disabled="props.disabled"
    :label="props.label"
    :model-value="props.modelValue"
    :name="props.name"
    :required="props.required"
    @update:model-value="emit('update:modelValue', $event)"
  />
</template>
