<script setup lang="ts">
type BreadcrumbItem = {
  href: string
  label: string
}

type HeaderAction = {
  href: string
  label: string
}

defineProps<{
  actions: HeaderAction[]
  breadcrumbs: BreadcrumbItem[]
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

    <template #default>
      <div v-if="breadcrumbs.length" class="nav-center">
        <UBreadcrumb :items="breadcrumbs" />
      </div>
    </template>

    <template #right>
      <div class="nav-right">
        <UButton
          v-for="action in actions"
          :key="action.href"
          color="neutral"
          variant="outline"
          @click="emit('navigate', action.href)"
        >
          {{ action.label }}
        </UButton>
      </div>
    </template>
  </UDashboardNavbar>
</template>

<style scoped>
.nav-center {
  min-width: 0;
  display: flex;
  align-items: center;
}

.nav-right {
  display: flex;
  flex-wrap: wrap;
  gap: 0.625rem;
  justify-content: flex-end;
}
</style>
