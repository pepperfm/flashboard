<script setup lang="ts">
import { computed, ref, watch } from 'vue'

type ExistingFileShape = {
  name?: string | null
  path?: string | null
  url?: string | null
}

type FileUploadModelValue = File | File[] | null | undefined

const props = defineProps<{
  accept?: string | null
  disabled?: boolean
  existingFiles?: ExistingFileShape[]
  modelValue?: FileUploadModelValue
  multiple?: boolean
  name?: string
  preview?: boolean
  required?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: FileUploadModelValue]
  'update:removeValue': [value: boolean]
}>()

const isRemovingExisting = ref(false)
const hasExistingFiles = computed(() => (props.existingFiles?.length ?? 0) > 0)
const hasSelectedFiles = computed(() => Array.isArray(props.modelValue) ? props.modelValue.length > 0 : Boolean(props.modelValue))
const showExistingFiles = computed(() => hasExistingFiles.value && !hasSelectedFiles.value && !isRemovingExisting.value)

watch(
  () => props.existingFiles,
  () => {
    if (!hasExistingFiles.value) {
      isRemovingExisting.value = false
    }
  },
  { deep: true },
)

function updateFiles(value: FileUploadModelValue) {
  isRemovingExisting.value = false
  emit('update:removeValue', false)
  emit('update:modelValue', value)
}

function removeExistingFiles() {
  isRemovingExisting.value = true
  emit('update:modelValue', null)
  emit('update:removeValue', true)
}

function undoRemoveExistingFiles() {
  isRemovingExisting.value = false
  emit('update:removeValue', false)
}
</script>

<template>
  <div class="space-y-2">
    <UFileUpload
      class="w-full"
      :accept="props.accept ?? undefined"
      :disabled="props.disabled"
      :model-value="props.modelValue"
      :multiple="props.multiple"
      :name="props.name"
      :preview="props.preview ?? true"
      :required="props.required"
      @update:model-value="updateFiles"
    />

    <div v-if="showExistingFiles" class="space-y-1">
      <div
        v-for="file in props.existingFiles"
        :key="file.path ?? file.url ?? file.name ?? ''"
        class="flex min-h-8 items-center justify-between gap-3 rounded-md border border-default px-2 py-1 text-sm"
      >
        <span class="truncate">{{ file.name ?? file.path ?? file.url }}</span>
        <UButton
          v-if="file.url"
          color="neutral"
          icon="i-lucide-external-link"
          size="xs"
          square
          :to="file.url"
          target="_blank"
          variant="ghost"
        />
        <UButton
          aria-label="Remove existing file"
          color="error"
          icon="i-lucide-trash-2"
          size="xs"
          square
          type="button"
          variant="ghost"
          @click="removeExistingFiles"
        />
      </div>
    </div>

    <div
      v-else-if="hasExistingFiles && isRemovingExisting"
      class="flex min-h-8 items-center justify-between gap-3 rounded-md border border-error/40 px-2 py-1 text-sm text-error"
    >
      <span class="truncate">Existing file will be removed</span>
      <UButton
        aria-label="Undo file removal"
        color="neutral"
        icon="i-lucide-rotate-ccw"
        size="xs"
        square
        type="button"
        variant="ghost"
        @click="undoRemoveExistingFiles"
      />
    </div>
  </div>
</template>
