<script setup lang="ts">
import type { DropdownMenuItem } from '@nuxt/ui'
import { chipColorMap } from '@/components/flashboard/themeOptions'

const props = defineProps<{
  content?: Record<string, unknown>
  items: DropdownMenuItem[][] | DropdownMenuItem[]
  open?: boolean
  ui?: Record<string, unknown>
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

function chipStyle(item: DropdownMenuItem) {
  const colorName = (item as { chip?: keyof typeof chipColorMap }).chip

  return colorName ? chipColorMap[colorName] : null
}
</script>

<template>
  <UDropdownMenu
    :open="props.open"
    :items="props.items"
    :content="props.content"
    :ui="props.ui"
    @update:open="emit('update:open', $event)"
  >
    <slot />

    <template
      v-for="(_, name) in $slots"
      :key="name"
      #[name]="slotData"
    >
      <slot
        v-if="name !== 'default' && name !== 'chip-leading'"
        :name="name"
        v-bind="slotData"
      />
    </template>

    <template #chip-leading="{ item }">
      <div
        v-if="chipStyle(item)"
        class="inline-flex size-5 shrink-0 items-center justify-center"
      >
        <span
          class="size-2 rounded-full ring ring-bg bg-(--chip-light) dark:bg-(--chip-dark)"
          :style="chipStyle(item)"
        />
      </div>
    </template>
  </UDropdownMenu>
</template>
