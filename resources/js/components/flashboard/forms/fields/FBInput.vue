<script setup lang="ts">
const props = withDefaults(defineProps<{
  disabled?: boolean
  modelValue?: string | number | null
  name?: string
  placeholder?: string
  required?: boolean
  type?: string
}>(), {
  type: 'text',
})

const emit = defineEmits<{
  'update:modelValue': [value: string | number | null | undefined]
}>()
</script>

<template>
  <UInput
    class="w-full"
    :disabled="props.disabled"
    :model-value="props.modelValue"
    :name="props.name"
    :placeholder="props.placeholder"
    :required="props.required"
    :type="props.type"
    @update:model-value="emit('update:modelValue', $event)"
  />
</template>
