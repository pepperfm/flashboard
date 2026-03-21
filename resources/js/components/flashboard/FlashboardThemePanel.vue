<script setup lang="ts">
import { chipColorMap, themeOptionLabel } from '@/components/flashboard/themeOptions'

type ThemeMode = 'light' | 'dark' | 'system'

const props = defineProps<{
  neutralColor: string
  neutralOptions: readonly string[]
  primaryColor: string
  primaryOptions: readonly string[]
  radius: number
  radiusOptions: readonly number[]
  themeMode: ThemeMode
}>()

const emit = defineEmits<{
  'update:neutralColor': [value: string]
  'update:primaryColor': [value: string]
  'update:radius': [value: number]
  'update:themeMode': [value: ThemeMode]
}>()

function chipStyle(color: string) {
  return chipColorMap[color as keyof typeof chipColorMap] ?? null
}
</script>

<template>
  <UCard
    variant="outline"
    :ui="{
      root: 'w-[36rem] max-w-[calc(100vw-2rem)]',
      body: 'p-5',
    }"
  >
    <div class="theme-panel">
      <section class="theme-section">
        <div class="theme-heading">
          <h3>Primary</h3>
        </div>

        <div class="theme-grid">
          <UButton
            v-for="color in primaryOptions"
            :key="`primary-${color}`"
            color="neutral"
            :variant="primaryColor === color ? 'subtle' : 'outline'"
            class="theme-option"
            @click="emit('update:primaryColor', color)"
          >
            <span
              class="theme-dot"
              :style="chipStyle(color) ?? undefined"
            />
            <span>{{ themeOptionLabel(color) }}</span>
          </UButton>
        </div>
      </section>

      <section class="theme-section">
        <div class="theme-heading">
          <h3>Neutral</h3>
        </div>

        <div class="theme-grid">
          <UButton
            v-for="color in neutralOptions"
            :key="`neutral-${color}`"
            color="neutral"
            :variant="neutralColor === color ? 'subtle' : 'outline'"
            class="theme-option"
            @click="emit('update:neutralColor', color)"
          >
            <span
              class="theme-dot"
              :style="chipStyle(color) ?? undefined"
            />
            <span>{{ themeOptionLabel(color) }}</span>
          </UButton>
        </div>
      </section>

      <section class="theme-section">
        <div class="theme-heading">
          <h3>Radius</h3>
        </div>

        <div class="radius-grid">
          <UButton
            v-for="value in radiusOptions"
            :key="`radius-${value}`"
            color="neutral"
            :variant="radius === value ? 'subtle' : 'outline'"
            class="radius-option"
            @click="emit('update:radius', value)"
          >
            {{ value }}
          </UButton>
        </div>
      </section>

      <section class="theme-section">
        <div class="theme-heading">
          <h3>Appearance</h3>
        </div>

        <div class="appearance-grid">
          <UButton
            color="neutral"
            :variant="themeMode === 'light' ? 'subtle' : 'outline'"
            icon="i-lucide-sun-medium"
            class="appearance-option"
            @click="emit('update:themeMode', 'light')"
          >
            Light
          </UButton>

          <UButton
            color="neutral"
            :variant="themeMode === 'dark' ? 'subtle' : 'outline'"
            icon="i-lucide-moon-star"
            class="appearance-option"
            @click="emit('update:themeMode', 'dark')"
          >
            Dark
          </UButton>

          <UButton
            color="neutral"
            :variant="themeMode === 'system' ? 'subtle' : 'outline'"
            icon="i-lucide-monitor-cog"
            class="appearance-option"
            @click="emit('update:themeMode', 'system')"
          >
            System
          </UButton>
        </div>
      </section>
    </div>
  </UCard>
</template>

<style scoped>
.theme-panel {
  display: grid;
  gap: 1.5rem;
}

.theme-section {
  display: grid;
  gap: 0.875rem;
}

.theme-heading h3 {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 700;
}

.theme-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.75rem;
}

.theme-option {
  justify-content: flex-start;
  min-height: 3.25rem;
}

.theme-dot {
  width: 0.9rem;
  height: 0.9rem;
  border-radius: 999px;
  flex-shrink: 0;
  background: var(--chip-light);
  box-shadow: 0 0 0 1px var(--ui-border-muted);
}

:global(.dark) .theme-dot {
  background: var(--chip-dark);
}

.radius-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 0.75rem;
}

.radius-option,
.appearance-option {
  justify-content: center;
  min-height: 3rem;
}

.appearance-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.75rem;
}

@media (max-width: 720px) {
  .theme-grid,
  .appearance-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .radius-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
</style>
