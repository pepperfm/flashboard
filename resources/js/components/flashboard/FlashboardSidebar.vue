<script setup lang="ts">
import type { DropdownMenuItem, NavigationMenuItem } from '@nuxt/ui'
import FlashboardDropdownMenu from '@/components/flashboard/FlashboardDropdownMenu.vue'
import { computed, onMounted, ref, watch } from 'vue'

const THEME_STORAGE_KEY = 'flashboard-theme'
const THEME_PRIMARY_STORAGE_KEY = 'flashboard-theme-primary'
const THEME_NEUTRAL_STORAGE_KEY = 'flashboard-theme-neutral'
const THEME_RADIUS_STORAGE_KEY = 'flashboard-theme-radius'
const PRIMARY_OPTIONS = [
  'black',
  'red',
  'orange',
  'amber',
  'yellow',
  'lime',
  'green',
  'emerald',
  'teal',
  'cyan',
  'sky',
  'blue',
  'indigo',
  'violet',
  'purple',
  'fuchsia',
  'pink',
  'rose',
] as const
const NEUTRAL_OPTIONS = ['slate', 'gray', 'zinc', 'neutral', 'stone', 'taupe', 'mauve', 'mist', 'olive'] as const
const RADIUS_OPTIONS = [0, 0.125, 0.25, 0.375, 0.5] as const

type NavigationItem = {
  href?: string
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
const userMenuOpen = ref(false)
const themeMode = ref<ThemeMode>('system')
const primaryColor = ref<string>(appConfig.ui.colors.primary)
const neutralColor = ref<string>(appConfig.ui.colors.neutral)
const radius = ref<number>(0.25)

const userLabel = computed(() => {
  if (props.user === null) {
    return 'Operator'
  }

  return String(props.user)
})

const navigationItems = computed<NavigationMenuItem[][]>(() => [
  props.items.map((item, index) => ({
    label: item.label,
    icon: 'i-lucide-panel-left',
    active: isActive(item.href),
    onSelect: (event: Event) => {
      event.preventDefault()
      emit('navigate', item.href)
    },
    tooltip: item.label,
    value: `navigation-${index}`,
  })),
])

const userMenuItems = computed<DropdownMenuItem[][]>(() => [
  [
    {
      label: 'Theme',
      icon: 'i-lucide-palette',
      children: [
        {
          label: 'Primary',
          slot: 'chip',
          chip: primaryColor.value,
          content: { align: 'center', collisionPadding: 16 },
          children: PRIMARY_OPTIONS.map((color) => ({
            label: color,
            chip: color,
            slot: 'chip',
            type: 'checkbox',
            checked: primaryColor.value === color,
            onSelect: closeMenu(() => {
              primaryColor.value = color
            }),
          })),
        },
        {
          label: 'Neutral',
          slot: 'chip',
          chip: neutralColor.value,
          content: { align: 'end', collisionPadding: 16 },
          children: NEUTRAL_OPTIONS.map((color) => ({
            label: color,
            chip: color,
            slot: 'chip',
            type: 'checkbox',
            checked: neutralColor.value === color,
            onSelect: closeMenu(() => {
              neutralColor.value = color
            }),
          })),
        },
        {
          label: 'Radius',
          content: { align: 'center', collisionPadding: 16 },
          children: RADIUS_OPTIONS.map((value) => ({
            label: String(value),
            type: 'checkbox',
            checked: radius.value === value,
            onSelect: closeMenu(() => {
              radius.value = value
            }),
          })),
        },
      ],
    },
    {
      label: 'Appearance',
      icon: 'i-lucide-sun-moon',
      children: [
        {
          label: 'Light',
          icon: 'i-lucide-sun-medium',
          type: 'checkbox',
          checked: themeMode.value === 'light',
          onSelect: closeMenu(() => {
            themeMode.value = 'light'
          }),
        },
        {
          label: 'Dark',
          icon: 'i-lucide-moon-star',
          type: 'checkbox',
          checked: themeMode.value === 'dark',
          onSelect: closeMenu(() => {
            themeMode.value = 'dark'
          }),
        },
        {
          label: 'System',
          icon: 'i-lucide-monitor-cog',
          type: 'checkbox',
          checked: themeMode.value === 'system',
          onSelect: closeMenu(() => {
            themeMode.value = 'system'
          }),
        },
      ],
    },
  ],
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

onMounted(() => {
  const storedMode = window.localStorage.getItem(THEME_STORAGE_KEY)
  const storedPrimary = window.localStorage.getItem(THEME_PRIMARY_STORAGE_KEY)
  const storedNeutral = window.localStorage.getItem(THEME_NEUTRAL_STORAGE_KEY)
  const storedRadius = window.localStorage.getItem(THEME_RADIUS_STORAGE_KEY)

  if (storedMode === 'light' || storedMode === 'dark' || storedMode === 'system') {
    themeMode.value = storedMode
  }

  if (storedPrimary && PRIMARY_OPTIONS.includes(storedPrimary as (typeof PRIMARY_OPTIONS)[number])) {
    primaryColor.value = storedPrimary
  }

  if (storedNeutral && NEUTRAL_OPTIONS.includes(storedNeutral as (typeof NEUTRAL_OPTIONS)[number])) {
    neutralColor.value = storedNeutral
  }

  if (storedRadius) {
    const parsedRadius = Number(storedRadius)

    if (RADIUS_OPTIONS.includes(parsedRadius as (typeof RADIUS_OPTIONS)[number])) {
      radius.value = parsedRadius
    }
  }

  applyTheme(themeMode.value)
  applyUiColors()
  applyRadius()
})

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
        <FlashboardDropdownMenu
          v-model:open="userMenuOpen"
          :items="userMenuItems"
          :content="{ align: 'center', collisionPadding: 12 }"
          :ui="{
            content: isCollapsed ? 'w-48' : 'w-(--reka-dropdown-menu-trigger-width)',
          }"
        >
          <UButton
            variant="ghost"
            color="neutral"
            :label="isCollapsed ? undefined : userLabel"
            :block="!isCollapsed"
            :square="isCollapsed"
            icon="i-lucide-user-round"
          />
        </FlashboardDropdownMenu>
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
  flex-direction: column;
  gap: 0.5rem;
  padding: 0.75rem;
  border-top: 1px solid rgba(120, 130, 150, 0.16);
}
</style>
