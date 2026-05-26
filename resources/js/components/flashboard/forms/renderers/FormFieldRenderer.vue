<script setup lang="ts">
import { computed } from 'vue'
import {
  isInlineBooleanFieldRenderer,
  resolveFormFieldRenderer,
  resolveFormFieldRendererProps,
  type FormFieldShape,
} from '@/components/flashboard/forms/renderers/resolveFormFieldRenderer'

const props = defineProps<{
  error?: string
  field: FormFieldShape
  modelValue?: unknown
}>()

const emit = defineEmits<{
  'update:modelValue': [value: unknown]
}>()

const component = computed(() => resolveFormFieldRenderer(props.field))
const componentProps = computed(() => resolveFormFieldRendererProps(props.field))
const isInlineBooleanField = computed(() => isInlineBooleanFieldRenderer(props.field))
</script>

<template>
  <UFormField
    v-if="!isInlineBooleanField"
    :name="props.field.key"
    :label="props.field.label ?? props.field.key"
    :hint="props.field.hint"
    :help="props.field.help"
    :error="props.error"
    :required="props.field.required"
  >
    <component
      :is="component"
      :model-value="props.modelValue"
      v-bind="componentProps"
      @update:model-value="emit('update:modelValue', $event)"
    />
  </UFormField>

  <UFormField
    v-else
    :name="props.field.key"
    :error="props.error"
  >
    <component
      :is="component"
      :model-value="props.modelValue"
      v-bind="componentProps"
      @update:model-value="emit('update:modelValue', $event)"
    />
  </UFormField>
</template>
