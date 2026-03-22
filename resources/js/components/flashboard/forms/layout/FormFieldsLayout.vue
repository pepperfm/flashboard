<script setup lang="ts">
import { computed } from 'vue'
import FormFieldRenderer from '@/components/flashboard/forms/renderers/FormFieldRenderer.vue'
import type { FormFieldShape } from '@/components/flashboard/forms/renderers/resolveFormFieldRenderer'
import {
  resolveFormContainerLayout,
  resolveFormItemLayout,
  type FormContainerLayoutDefaults,
  type FormContainerLayoutShape,
} from '@/components/flashboard/forms/layout/resolveFormLayout'

const props = defineProps<{
  defaultLayout?: FormContainerLayoutDefaults
  errors?: Record<string, string>
  fields: FormFieldShape[]
  layout?: FormContainerLayoutShape
  state: Record<string, unknown>
}>()

const emit = defineEmits<{
  'update:field': [fieldKey: string, value: unknown]
}>()

const resolvedContainerLayout = computed(() => resolveFormContainerLayout(
  props.layout,
  props.defaultLayout,
))

function fieldError(fieldKey: string): string | undefined {
  const error = props.errors?.[fieldKey]

  return typeof error === 'string' ? error : undefined
}
</script>

<template>
  <div
    :class="resolvedContainerLayout.className"
    :style="resolvedContainerLayout.style"
  >
    <div
      v-for="field in props.fields"
      :key="field.key"
      :class="resolveFormItemLayout(resolvedContainerLayout, field.layout).className"
      :style="resolveFormItemLayout(resolvedContainerLayout, field.layout).style"
    >
      <FormFieldRenderer
        :field="field"
        :model-value="props.state[field.key]"
        :error="fieldError(field.key)"
        @update:model-value="emit('update:field', field.key, $event)"
      />
    </div>
  </div>
</template>
