<script setup lang="ts">
import FormContainerRenderer from '@/components/flashboard/forms/renderers/FormContainerRenderer.vue'
import FormFieldRenderer from '@/components/flashboard/forms/renderers/FormFieldRenderer.vue'
import type { FormFieldShape, FormNodeShape } from '@/components/flashboard/forms/renderers/resolveFormFieldRenderer'

const props = defineProps<{
  error?: string
  errors?: Record<string, string>
  node: FormNodeShape
  state: Record<string, unknown>
}>()

const emit = defineEmits<{
  'update:field': [fieldKey: string, value: unknown]
}>()

function isFieldNode(node: FormNodeShape): node is FormFieldShape {
  return (node.kind ?? 'field') === 'field'
}

function fieldError(field: FormFieldShape): string | undefined {
  if (props.error) {
    return props.error
  }

  const error = props.errors?.[field.key]

  return typeof error === 'string' ? error : undefined
}
</script>

<template>
  <FormFieldRenderer
    v-if="isFieldNode(props.node)"
    :field="props.node"
    :model-value="props.state[props.node.key]"
    :error="fieldError(props.node)"
    @update:model-value="emit('update:field', props.node.key, $event)"
  />

  <FormContainerRenderer
    v-else
    :node="props.node"
    :errors="props.errors"
    :state="props.state"
    @update:field="(fieldKey, value) => emit('update:field', fieldKey, value)"
  />
</template>
