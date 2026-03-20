<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import FlashboardNavbar from '@/components/flashboard/FlashboardNavbar.vue'
import FlashboardNotifications from '@/components/flashboard/FlashboardNotifications.vue'
import FlashboardScreenContent from '@/components/flashboard/FlashboardScreenContent.vue'
import FlashboardSidebar from '@/components/flashboard/FlashboardSidebar.vue'
import { computed } from 'vue'

const FLASHBOARD_CURRENT_URL_KEY = 'flashboard.current_url'
const FLASHBOARD_PREVIOUS_URL_KEY = 'flashboard.previous_url'

type LayoutAction = {
  behavior?: 'back' | 'link'
  color?: string
  fallbackHref?: string
  href: string
  icon?: string
  label: string
  variant?: string
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
  resource?: { name?: string; page?: string }
  table?: {
    dataset?: {
      columns?: Array<{ key: string; label: string }>
      rows?: Array<{ id: string | number; attributes: Record<string, unknown> }>
      routes?: {
        create?: string | null
      }
    }
  }
  form?: {
    mode?: string
    fields?: Array<{ key?: string; label?: string }>
    cancel?: {
      url?: string | null
    }
  } | null
  detail?: {
    entries?: Array<{ label?: string; value?: unknown }>
    relations?: Array<{ key?: string; label?: string; records?: Array<{ key: string | number; title: string }> }>
    routes?: {
      index?: string | null
    }
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

if (typeof window !== 'undefined') {
  const currentUrl = window.location.href
  const storedCurrentUrl = window.sessionStorage.getItem(FLASHBOARD_CURRENT_URL_KEY)

  if (storedCurrentUrl !== currentUrl) {
    if (storedCurrentUrl) {
      window.sessionStorage.setItem(FLASHBOARD_PREVIOUS_URL_KEY, storedCurrentUrl)
    }

    window.sessionStorage.setItem(FLASHBOARD_CURRENT_URL_KEY, currentUrl)
  }
}

const visibleBreadcrumbs = computed(() => {
  if (props.payload.page?.key === 'dashboard' || props.payload.page?.type === 'dashboard') {
    return []
  }

  if (props.payload.metadata.screen_key === 'dashboard') {
    return []
  }

  return props.layout.breadcrumbs
})

const navbarActions = computed<LayoutAction[]>(() => {
  const resourcePage = props.payload.resource?.page

  if (resourcePage === 'index' && props.payload.table?.dataset?.routes?.create) {
    return [
      {
        href: props.payload.table.dataset.routes.create,
        label: 'Create',
        icon: 'i-lucide-plus',
        behavior: 'link',
        color: 'primary',
        variant: 'solid',
      },
    ]
  }

  if (
    (resourcePage === 'create' || resourcePage === 'edit')
    && props.payload.form?.cancel?.url
  ) {
    return [
      {
        href: props.payload.form.cancel.url,
        label: 'Back',
        icon: 'i-lucide-chevron-left',
        behavior: 'back',
        color: 'neutral',
        fallbackHref: props.payload.form.cancel.url,
        variant: 'ghost',
      },
    ]
  }

  if (resourcePage === 'detail' && props.payload.detail?.routes?.index) {
    return [
      {
        href: props.payload.detail.routes.index,
        label: 'Back',
        icon: 'i-lucide-chevron-left',
        behavior: 'back',
        color: 'neutral',
        fallbackHref: props.payload.detail.routes.index,
        variant: 'ghost',
      },
    ]
  }

  return props.layout.header_actions
})

function goBack(fallbackHref?: string) {
  if (typeof window !== 'undefined') {
    const previousUrl = window.sessionStorage.getItem(FLASHBOARD_PREVIOUS_URL_KEY)

    if (previousUrl && previousUrl !== window.location.href) {
      router.get(previousUrl)
      return
    }

    if (window.history.length > 1) {
      window.history.back()
      return
    }
  }

  if (fallbackHref) {
    router.get(fallbackHref)
  }
}

function handleSidebarNavigate(href?: string) {
  if (!href) {
    return
  }

  router.get(href)
}

function handleNavbarAction(action?: LayoutAction) {
  if (!action) {
    return
  }

  if (action.behavior === 'back') {
    goBack(action.fallbackHref ?? action.href)
    return
  }

  if (action.href) {
    router.get(action.href)
  }
}

function logout() {
  router.post(props.panel.logout_url)
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
        @navigate="handleSidebarNavigate"
        @logout="logout"
      />

      <UDashboardPanel id="flashboard-main" class="screen-panel">
        <template #header>
          <FlashboardNavbar
            :actions="navbarActions"
            :title="props.layout.title"
            @navigate="handleNavbarAction"
          />
        </template>

        <template #body>
          <div class="content">
            <FlashboardNotifications :notifications="props.layout.notifications" />

            <div class="stack">
              <FlashboardScreenContent
                :breadcrumbs="visibleBreadcrumbs"
                :payload="props.payload"
              />
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
