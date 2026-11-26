<script setup lang="ts">
import { computed } from 'vue'
import { normalizeNumberInputValue } from './numberInputValue'

const props = withDefaults(defineProps<{
  disabled?: boolean
  modelValue?: string | number | null
  name?: string
  placeholder?: string
  readonly?: boolean
  required?: boolean
  type?: string
}>(), {
  type: 'text',
})

const emit = defineEmits<{
  'update:modelValue': [value: string | number | null | undefined]
}>()
const numberValue = computed(() => normalizeNumberInputValue(props.modelValue))
</script>

<template>
  <UInputNumber
    v-if="props.type === 'number'"
    class="w-full"
    :disabled="props.disabled"
    :model-value="numberValue"
    :name="props.name"
    :placeholder="props.placeholder"
    :readonly="props.readonly"
    :required="props.required"
    :step-snapping="false"
    :format-options="{ maximumFractionDigits: 20 }"
    :disable-wheel-change="true"
    @update:model-value="emit('update:modelValue', normalizeNumberInputValue($event) ?? null)"
  />
  <UInput
    v-else
    class="w-full"
    :disabled="props.disabled"
    :model-value="props.modelValue"
    :name="props.name"
    :placeholder="props.placeholder"
    :readonly="props.readonly"
    :required="props.required"
    :type="props.type"
    @update:model-value="emit('update:modelValue', $event)"
  />
</template>
