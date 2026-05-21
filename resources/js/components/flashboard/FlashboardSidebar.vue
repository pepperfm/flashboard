<script setup lang="ts">
import type { DropdownMenuItem, NavigationMenuItem } from '@nuxt/ui'
import FlashboardThemePanel from '@/components/flashboard/FlashboardThemePanel.vue'
import { NEUTRAL_OPTIONS, PRIMARY_OPTIONS, RADIUS_OPTIONS } from '@/components/flashboard/themeOptions'
import { computed, ref, watch } from 'vue'

const THEME_STORAGE_KEY = 'flashboard-theme'
const THEME_PRIMARY_STORAGE_KEY = 'flashboard-theme-primary'
const THEME_NEUTRAL_STORAGE_KEY = 'flashboard-theme-neutral'
const THEME_RADIUS_STORAGE_KEY = 'flashboard-theme-radius'
const DEFAULT_NAVIGATION_ICON = 'i-lucide-panel-left'

type NavigationItem = {
  href?: string
  icon?: string | null
  label: string
}

type ThemeMode = 'light' | 'dark' | 'system'

const props = defineProps<{
  items: NavigationItem[]
  panelName: string
  user: string | number | null
}>()

const emit = defineEmits<{
  navigate: [href?: string]
  logout: []
}>()

const appConfig = useAppConfig()
const collapsed = ref(false)
const themePanelOpen = ref(false)
const userMenuOpen = ref(false)
const themeMode = ref<ThemeMode>(resolveThemeMode())
const primaryColor = ref<string>(resolveThemeColor(THEME_PRIMARY_STORAGE_KEY, PRIMARY_OPTIONS, appConfig.ui.colors.primary))
const neutralColor = ref<string>(resolveThemeColor(THEME_NEUTRAL_STORAGE_KEY, NEUTRAL_OPTIONS, appConfig.ui.colors.neutral))
const radius = ref<number>(resolveThemeRadius())

const userLabel = computed(() => {
  if (props.user === null) {
    return 'Operator'
  }

  return String(props.user)
})

const navigationItems = computed<NavigationMenuItem[][]>(() => [
  props.items.map((item, index) => ({
    label: item.label,
    icon: resolveNavigationIcon(item.icon),
    active: isActive(item.href),
    onSelect: (event: Event) => {
      event.preventDefault()
      emit('navigate', item.href)
    },
    tooltip: item.label,
    value: `navigation-${index}`,
  })),
])

function resolveNavigationIcon(icon?: string | null): string {
  if (!icon) {
    return DEFAULT_NAVIGATION_ICON
  }

  return icon.startsWith('i-') ? icon : `i-${icon}`
}

const userMenuItems = computed<DropdownMenuItem[][]>(() => [
  [
    {
      label: 'Logout',
      icon: 'i-lucide-log-out',
      color: 'error',
      onSelect: closeMenu(() => {
        emit('logout')
      }),
    },
  ],
])

if (typeof window !== 'undefined') {
  applyTheme(themeMode.value)
  applyUiColors()
  applyRadius()
}

watch(themeMode, (value) => {
  window.localStorage.setItem(THEME_STORAGE_KEY, value)
  applyTheme(value)
})

watch(primaryColor, (value) => {
  window.localStorage.setItem(THEME_PRIMARY_STORAGE_KEY, value)
  applyUiColors()
})

watch(neutralColor, (value) => {
  window.localStorage.setItem(THEME_NEUTRAL_STORAGE_KEY, value)
  applyUiColors()
})

watch(radius, (value) => {
  window.localStorage.setItem(THEME_RADIUS_STORAGE_KEY, String(value))
  applyRadius()
})

function closeMenu(handler: () => void): (event: Event) => void {
  return (event: Event) => {
    event.preventDefault()
    handler()
    userMenuOpen.value = false
  }
}

function resolveThemeMode(): ThemeMode {
  if (typeof window === 'undefined') {
    return 'system'
  }

  const storedMode = window.localStorage.getItem(THEME_STORAGE_KEY)

  return storedMode === 'light' || storedMode === 'dark' || storedMode === 'system'
    ? storedMode
    : 'system'
}

/**
 * @param readonly string[] $allowedOptions
 */
function resolveThemeColor(storageKey: string, allowedOptions: readonly string[], fallback: string): string {
  if (typeof window === 'undefined') {
    return fallback
  }

  const storedColor = window.localStorage.getItem(storageKey)

  return storedColor && allowedOptions.includes(storedColor)
    ? storedColor
    : fallback
}

function resolveThemeRadius(): number {
  if (typeof window === 'undefined') {
    return 0.25
  }

  const storedRadius = window.localStorage.getItem(THEME_RADIUS_STORAGE_KEY)

  if (!storedRadius) {
    return 0.25
  }

  const parsedRadius = Number(storedRadius)

  return RADIUS_OPTIONS.includes(parsedRadius as (typeof RADIUS_OPTIONS)[number])
    ? parsedRadius
    : 0.25
}

function isActive(href?: string): boolean {
  if (!href || typeof window === 'undefined') {
    return false
  }

  const targetPath = new URL(href, window.location.origin).pathname

  return window.location.pathname === targetPath
}

function applyTheme(mode: ThemeMode): void {
  const shouldUseDark = mode === 'dark'
    || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)

  document.documentElement.classList.toggle('dark', shouldUseDark)
  document.documentElement.style.colorScheme = shouldUseDark ? 'dark' : 'light'
}

function applyUiColors(): void {
  appConfig.ui.colors.primary = primaryColor.value
  appConfig.ui.colors.neutral = neutralColor.value
}

function applyRadius(): void {
  document.documentElement.style.setProperty('--ui-radius', `${radius.value}rem`)
}

</script>

<template>
  <UDashboardSidebar
    id="flashboard-sidebar"
    v-model:collapsed="collapsed"
    collapsible
    :resizable="true"
    :size="5"
    :collapsed-size="5"
    class="border-r border-default bg-elevated/30 sm:data-[collapsed=false]:!w-[17rem] sm:data-[collapsed=true]:!w-[5rem] max-sm:!w-[18rem]"
  >
    <template #header="{ collapsed: isCollapsed }">
      <div class="sidebar-header">
        <UButton
          variant="ghost"
          color="neutral"
          class="brand-button"
          @click="emit('navigate', items[0]?.href)"
        >
          <UIcon name="i-lucide-layout-dashboard" class="size-5" />
          <span v-if="!isCollapsed">{{ panelName }}</span>
        </UButton>
      </div>
    </template>

    <template #default="{ collapsed: isCollapsed }">
      <div class="nav-shell">
        <UNavigationMenu
          :items="navigationItems"
          orientation="vertical"
          :collapsed="isCollapsed"
          :tooltip="isCollapsed"
          highlight
          highlight-color="primary"
          class="nav-menu"
          :ui="{
            root: 'flex flex-col gap-2 px-2',
            list: 'flex flex-col gap-2',
            link: 'rounded-md px-3 py-2 text-sm font-medium hover:bg-primary-500/10 data-[active=true]:bg-primary-500/10 data-[active=true]:text-primary-600 dark:data-[active=true]:text-primary-400',
          }"
        />
      </div>
    </template>

    <template #footer="{ collapsed: isCollapsed }">
      <div class="sidebar-footer">
        <UPopover
          v-model:open="themePanelOpen"
          :content="{ side: isCollapsed ? 'right' : 'top', align: isCollapsed ? 'start' : 'center', sideOffset: 10, collisionPadding: 12 }"
        >
          <UButton
            variant="ghost"
            color="neutral"
            square
            icon="i-lucide-palette"
            aria-label="Theme settings"
          />

          <template #content>
            <FlashboardThemePanel
              :primary-options="PRIMARY_OPTIONS"
              :primary-color="primaryColor"
              :neutral-options="NEUTRAL_OPTIONS"
              :neutral-color="neutralColor"
              :radius-options="RADIUS_OPTIONS"
              :radius="radius"
              :theme-mode="themeMode"
              @update:primary-color="primaryColor = $event"
              @update:neutral-color="neutralColor = $event"
              @update:radius="radius = $event"
              @update:theme-mode="themeMode = $event"
            />
          </template>
        </UPopover>

        <UDropdownMenu
          v-model:open="userMenuOpen"
          :items="userMenuItems"
          :content="{ align: 'center', collisionPadding: 12 }"
          :ui="{
            content: 'w-48',
          }"
        >
          <UButton
            variant="ghost"
            color="neutral"
            square
            icon="i-lucide-user-round"
          />
        </UDropdownMenu>
      </div>
    </template>
  </UDashboardSidebar>
</template>

<style scoped>
.sidebar-header {
  display: flex;
  width: 100%;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  padding: 1rem 0.75rem;
}

.brand-button {
  justify-content: flex-start;
  width: 100%;
  font-size: 0.875rem;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.nav-shell {
  display: flex;
  height: 100%;
  flex-direction: column;
}

.nav-menu {
  flex: 1;
  overflow-y: auto;
  padding-block: 1rem;
}

.sidebar-footer {
  display: flex;
  width: 100%;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.75rem;
  border-top: 1px solid rgba(120, 130, 150, 0.16);
}
</style>
