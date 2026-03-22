<script setup lang="ts">
type SelectOptionValue = string | number | boolean

type SelectOptionShape = {
  label?: string
  value?: SelectOptionValue
}

const props = defineProps<{
  disabled?: boolean
  items?: SelectOptionShape[]
  modelValue?: SelectOptionValue | null
  name?: string
  placeholder?: string
  required?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: SelectOptionValue | null | undefined]
}>()
</script>

<template>
  <USelect
    class="w-full"
    :disabled="props.disabled"
    :items="props.items ?? []"
    :model-value="props.modelValue"
    :name="props.name"
    :placeholder="props.placeholder"
    :required="props.required"
    @update:model-value="emit('update:modelValue', $event)"
  />
</template>
