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
  user: string | number | null
}>()

const emit = defineEmits<{
  navigate: [href?: string]
  logout: []
}>()
</script>

<template>
  <UDashboardNavbar :title="title">
    <template #left>
      <div class="nav-left">
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

        <UBadge v-if="user !== null" color="neutral" variant="soft">
          {{ user }}
        </UBadge>

        <UButton color="neutral" variant="soft" @click="emit('logout')">
          Logout
        </UButton>
      </div>
    </template>
  </UDashboardNavbar>
</template>

<style scoped>
.nav-left {
  min-width: 0;
}

.nav-right {
  display: flex;
  flex-wrap: wrap;
  gap: 0.625rem;
  justify-content: flex-end;
}
</style>
