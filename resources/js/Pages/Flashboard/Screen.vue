<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import FlashboardNavbar from '@/components/flashboard/FlashboardNavbar.vue'
import FlashboardNotifications from '@/components/flashboard/FlashboardNotifications.vue'
import FlashboardOverview from '@/components/flashboard/FlashboardOverview.vue'
import FlashboardScreenContent from '@/components/flashboard/FlashboardScreenContent.vue'
import FlashboardSidebar from '@/components/flashboard/FlashboardSidebar.vue'
import { computed } from 'vue'

type LayoutAction = {
  href: string
  label: string
}

type LayoutState = {
  kind: string
  message: string
}

type LayoutShape = {
  breadcrumbs: Array<{ href: string; label: string }>
  header_actions: LayoutAction[]
  navigation: Array<{ href?: string; label?: string }>
  notifications: Array<{ level: string; message: string }>
  state: LayoutState
  theme: { name?: string; tokens?: Record<string, string> }
  title: string
}

type PayloadShape = {
  metadata: {
    screen_key: string
    screen_kind: string
  }
  schema_version?: string
  page?: { key?: string; title: string; type?: string }
  workspace?: { title?: string; description?: string }
  resource?: { page?: string }
  table?: {
    dataset?: {
      columns?: Array<{ key: string; label: string }>
      rows?: Array<{ id: string | number; attributes: Record<string, unknown> }>
    }
  }
  form?: {
    mode?: string
    fields?: Array<{ key?: string; label?: string }>
  } | null
  detail?: {
    entries?: Array<{ label?: string; value?: unknown }>
    relations?: Array<{ key?: string; label?: string; records?: Array<{ key: string | number; title: string }> }>
  } | null
}

const props = defineProps<{
  layout: LayoutShape
  panel: {
    name: string
    path: string
    logout_url: string
    route_name_prefix: string
  }
  payload: PayloadShape
  user: string | number | null
  version: string
}>()

const visibleBreadcrumbs = computed(() => {
  if (props.payload.page?.key === 'dashboard' || props.payload.page?.type === 'dashboard') {
    return []
  }

  if (props.payload.metadata.screen_key === 'dashboard') {
    return []
  }

  return props.layout.breadcrumbs
})

function visit(href?: string) {
  if (!href) {
    return
  }

  router.visit(href)
}

function logout() {
  router.visit(props.panel.logout_url, {
    method: 'post',
  })
}
</script>

<template>
  <Head :title="props.layout.title" />

  <UApp>
    <UDashboardGroup
      storage="local"
      storage-key="flashboard"
      unit="rem"
      class="screen-shell"
    >
      <FlashboardSidebar
        :items="props.layout.navigation"
        :panel-name="props.panel.name"
        :user="props.user"
        @navigate="visit"
        @logout="logout"
      />

      <UDashboardPanel id="flashboard-main" class="screen-panel">
        <template #header>
          <FlashboardNavbar
            :actions="props.layout.header_actions"
            :breadcrumbs="visibleBreadcrumbs"
            :title="props.layout.title"
            @navigate="visit"
          />
        </template>

        <template #body>
          <div class="content">
            <FlashboardNotifications :notifications="props.layout.notifications" />

            <div class="stack">
              <FlashboardOverview
                :panel="props.panel"
                :payload="props.payload"
                :state-message="props.layout.state.message"
              />

              <FlashboardScreenContent :payload="props.payload" />
            </div>
          </div>
        </template>
      </UDashboardPanel>
    </UDashboardGroup>
  </UApp>
</template>

<style scoped>
:global(:root) {
  color-scheme: light;
  font-family: "Inter", "Segoe UI", sans-serif;
}

:global(body) {
  margin: 0;
  min-height: 100vh;
  background: rgb(250 250 250);
  color: rgb(24 24 27);
}

:global(.dark body) {
  background: rgb(10 10 11);
  color: rgb(244 244 245);
}

.screen-shell {
  min-height: 100vh;
  background: color-mix(in srgb, var(--ui-bg) 92%, transparent);
}

.screen-panel {
  min-height: 100vh;
}

.content {
  padding: 1.25rem;
}

.stack {
  display: grid;
  gap: 1rem;
}

@media (max-width: 900px) {
  .content {
    padding: 1rem;
  }
}
</style>
