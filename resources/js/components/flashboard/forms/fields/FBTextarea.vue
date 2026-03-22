<script setup lang="ts">
const props = withDefaults(defineProps<{
  autoresize?: boolean
  disabled?: boolean
  modelValue?: string | number | null
  name?: string
  placeholder?: string
  required?: boolean
  rows?: number
}>(), {
  autoresize: true,
  rows: 4,
})

const emit = defineEmits<{
  'update:modelValue': [value: string | number | null | undefined]
}>()
</script>

<template>
  <UTextarea
    class="w-full"
    :autoresize="props.autoresize"
    :disabled="props.disabled"
    :model-value="props.modelValue"
    :name="props.name"
    :placeholder="props.placeholder"
    :required="props.required"
    :rows="props.rows"
    @update:model-value="emit('update:modelValue', $event)"
  />
</template>
