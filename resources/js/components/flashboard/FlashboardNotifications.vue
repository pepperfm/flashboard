<script setup lang="ts">
defineProps<{
  notifications: Array<{ level: string; message: string }>
}>()
</script>

<template>
  <div v-if="notifications.length" class="notifications">
    <UAlert
      v-for="notification in notifications"
      :key="`${notification.level}-${notification.message}`"
      :title="notification.level.toUpperCase()"
      :description="notification.message"
      :color="notification.level === 'error' ? 'error' : notification.level === 'warning' ? 'warning' : 'neutral'"
      variant="soft"
    />
  </div>
</template>

<style scoped>
.notifications {
  display: grid;
  gap: 0.875rem;
  margin-bottom: 0.875rem;
}
</style>
