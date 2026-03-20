<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import FlashboardNavbar from '@/components/flashboard/FlashboardNavbar.vue'
import FlashboardNotifications from '@/components/flashboard/FlashboardNotifications.vue'
import FlashboardOverview from '@/components/flashboard/FlashboardOverview.vue'
import FlashboardScreenContent from '@/components/flashboard/FlashboardScreenContent.vue'
import FlashboardSidebar from '@/components/flashboard/FlashboardSidebar.vue'

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
  page?: { title: string }
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
    <UDashboardGroup storage="local" storage-key="flashboard">
      <FlashboardSidebar
        :items="props.layout.navigation"
        :panel-name="props.panel.name"
        @navigate="visit"
      />

      <UDashboardPanel id="flashboard-main">
        <template #header>
          <FlashboardNavbar
            :actions="props.layout.header_actions"
            :breadcrumbs="props.layout.breadcrumbs"
            :title="props.layout.title"
            :user="props.user"
            @navigate="visit"
            @logout="logout"
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
                :theme-name="props.layout.theme.name"
                :version="props.version"
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
  font-family: "Iowan Old Style", "Palatino Linotype", "Book Antiqua", serif;
}

:global(body) {
  margin: 0;
  min-height: 100vh;
  background:
    radial-gradient(circle at top left, rgba(235, 199, 84, 0.24), transparent 32%),
    linear-gradient(135deg, #f4efe2 0%, #e8dfcf 46%, #d7cab4 100%);
  color: #1f1a16;
}

.content {
  padding: 1.5rem;
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
