<script setup lang="ts">
type BreadcrumbItem = {
  href: string
  label: string
}

type HeaderAction = {
  color?: string
  href: string
  icon?: string
  label: string
  variant?: string
}

defineProps<{
  actions: HeaderAction[]
  title: string
}>()

const emit = defineEmits<{
  navigate: [href?: string]
}>()
</script>

<template>
  <UDashboardNavbar :title="title" class="border-b border-default bg-default/80 backdrop-blur">
    <template #leading>
      <UDashboardSidebarCollapse variant="subtle" />
    </template>

    <template #right>
      <div class="nav-right">
        <UButton
          v-for="action in actions"
          :key="`${action.href}-${action.label}`"
          :color="action.color ?? 'neutral'"
          :icon="action.icon"
          :variant="action.variant ?? 'outline'"
          @click="emit('navigate', action.href)"
        >
          {{ action.label }}
        </UButton>
      </div>
    </template>
  </UDashboardNavbar>
</template>

<style scoped>
.nav-right {
  display: flex;
  flex-wrap: wrap;
  gap: 0.625rem;
  justify-content: flex-end;
}
</style>
