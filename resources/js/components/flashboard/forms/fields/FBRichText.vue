<script setup lang="ts">
type RichTextContent = string | Record<string, unknown> | Record<string, unknown>[] | null | undefined

const props = withDefaults(defineProps<{
  contentFormat?: 'html' | 'markdown' | 'json'
  disabled?: boolean
  modelValue?: RichTextContent
  name?: string
  placeholder?: string
  required?: boolean
}>(), {
  contentFormat: 'html',
})

const emit = defineEmits<{
  'update:modelValue': [value: RichTextContent]
}>()
</script>

<template>
  <UEditor
    class="w-full"
    :content-type="props.contentFormat"
    :editable="!props.disabled"
    :model-value="props.modelValue"
    :placeholder="props.placeholder"
    @update:model-value="emit('update:modelValue', $event)"
  />
</template>
