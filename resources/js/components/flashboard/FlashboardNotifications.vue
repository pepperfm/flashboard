<script setup lang="ts">
import { useToast } from '@nuxt/ui/composables'
import { watch } from 'vue'

type NotificationShape = {
  id?: string
  level: string
  message: string
}

type ToastColor = 'error' | 'info' | 'neutral' | 'success' | 'warning'

const props = defineProps<{
  notifications: NotificationShape[]
}>()

const toast = useToast()
const shownNotificationIds = new Set<string>()

watch(
  () => props.notifications,
  (notifications) => {
    for (const [index, notification] of notifications.entries()) {
      const id = notification.id ?? `${notification.level}:${notification.message}:${index}`

      if (shownNotificationIds.has(id)) {
        continue
      }

      shownNotificationIds.add(id)
      toast.add({
        id,
        title: notification.message,
        color: notificationColor(notification.level),
        icon: notificationIcon(notification.level),
        duration: 2000,
        type: 'foreground',
      })
    }
  },
  { deep: true, immediate: true },
)

function notificationColor(level: string): ToastColor {
  if (level === 'error' || level === 'warning' || level === 'success' || level === 'info') {
    return level
  }

  return 'neutral'
}

function notificationIcon(level: string): string {
  if (level === 'success') {
    return 'i-lucide-check-circle'
  }

  if (level === 'error') {
    return 'i-lucide-circle-x'
  }

  if (level === 'warning') {
    return 'i-lucide-triangle-alert'
  }

  return 'i-lucide-info'
}
</script>

<template>
  <div aria-hidden="true" class="notifications-sentinel" />
</template>

<style scoped>
.notifications-sentinel {
  display: none;
}
</style>
