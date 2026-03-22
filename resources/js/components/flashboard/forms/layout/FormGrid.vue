<script setup lang="ts">
const props = defineProps<{
  className?: string[]
  style?: Record<string, string>
}>()
</script>

<template>
  <div :class="props.className" :style="props.style">
    <slot />
  </div>
</template>
