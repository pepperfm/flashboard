<script setup lang="ts">
type NavigationItem = {
  href?: string
  label: string
}

defineProps<{
  items: NavigationItem[]
  panelName: string
}>()

const emit = defineEmits<{
  navigate: [href?: string]
}>()
</script>

<template>
  <UDashboardSidebar :resizable="true" :collapsible="true" :default-size="18" :min-size="14" :max-size="26">
    <template #header>
      <div class="sidebar-header">
        <p class="eyebrow">Nuxt UI Navigation</p>
        <p class="brand">{{ panelName }}</p>
      </div>
    </template>

    <div class="nav-stack">
      <UButton
        v-for="item in items"
        :key="`${item.label}-${item.href ?? 'no-href'}`"
        block
        color="neutral"
        variant="ghost"
        @click="emit('navigate', item.href)"
      >
        {{ item.label }}
      </UButton>
    </div>
  </UDashboardSidebar>
</template>

<style scoped>
.sidebar-header {
  padding: 0.25rem 0.25rem 0.5rem;
}

.eyebrow {
  margin: 0 0 0.5rem;
  font-size: 0.75rem;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: #75624d;
}

.brand {
  margin: 0;
  font-size: 1.35rem;
  line-height: 1;
}

.nav-stack {
  display: grid;
  gap: 0.625rem;
}
</style>
