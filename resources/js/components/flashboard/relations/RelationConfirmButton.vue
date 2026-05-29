<script setup lang="ts">
import { ref } from 'vue'

const props = withDefaults(defineProps<{
  color?: 'error' | 'neutral' | 'primary' | 'warning'
  disabled?: boolean
  icon: string
  label: string
  message?: string
  submitLabel?: string
}>(), {
  color: 'warning',
  disabled: false,
  message: 'Continue?',
  submitLabel: 'Continue',
})

const emit = defineEmits<{
  confirm: []
}>()

const open = ref(false)

function confirm() {
  open.value = false
  emit('confirm')
}
</script>

<template>
  <UPopover
    v-model:open="open"
    :content="{ align: 'end', collisionPadding: 12, side: 'top', sideOffset: 8 }"
    :ui="{ content: 'w-56 p-3' }"
  >
    <UButton
      :aria-label="props.label"
      :color="props.color"
      :disabled="props.disabled"
      :icon="props.icon"
      :title="props.label"
      size="xs"
      square
      variant="ghost"
    />

    <template #content>
      <div class="grid gap-3">
        <p class="m-0 text-sm font-medium leading-5 text-highlighted">
          {{ props.message }}
        </p>
        <div class="flex justify-end gap-2">
          <UButton
            color="neutral"
            label="Cancel"
            size="xs"
            variant="ghost"
            @click="open = false"
          />
          <UButton
            :color="props.color"
            :label="props.submitLabel"
            size="xs"
            variant="solid"
            @click="confirm"
          />
        </div>
      </div>
    </template>
  </UPopover>
</template>
