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
  'update:field': [fieldKey: string, value: unknown]
  'update:modelValue': [value: unknown]
}>()

const component = computed(() => resolveFormFieldRenderer(props.field))
const componentProps = computed(() => resolveFormFieldRendererProps(props.field))
const isInlineBooleanField = computed(() => isInlineBooleanFieldRenderer(props.field))

function fileRemoveFieldKey(fieldKey: string): string {
  return `${fieldKey}__remove`
}
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
      @update:remove-value="emit('update:field', fileRemoveFieldKey(props.field.key), $event)"
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
      @update:remove-value="emit('update:field', fileRemoveFieldKey(props.field.key), $event)"
    />
  </UFormField>
</template>
