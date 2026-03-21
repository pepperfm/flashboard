<script setup lang="ts">
import type { DropdownMenuItem } from '@nuxt/ui'

const chipColorMap = {
  black: {
    '--chip-light': 'var(--color-black, #000)',
    '--chip-dark': 'var(--color-black, #000)',
  },
  red: {
    '--chip-light': 'var(--color-red-500, oklch(63.7% 0.237 25.331))',
    '--chip-dark': 'var(--color-red-400, oklch(70.4% 0.191 22.216))',
  },
  orange: {
    '--chip-light': 'var(--color-orange-500, oklch(70.5% 0.213 47.604))',
    '--chip-dark': 'var(--color-orange-400, oklch(75% 0.183 55.934))',
  },
  amber: {
    '--chip-light': 'var(--color-amber-500, oklch(76.9% 0.188 70.08))',
    '--chip-dark': 'var(--color-amber-400, oklch(82.8% 0.189 84.429))',
  },
  yellow: {
    '--chip-light': 'var(--color-yellow-500, oklch(79.5% 0.184 86.047))',
    '--chip-dark': 'var(--color-yellow-400, oklch(85.2% 0.199 91.936))',
  },
  lime: {
    '--chip-light': 'var(--color-lime-500, oklch(76.8% 0.233 130.85))',
    '--chip-dark': 'var(--color-lime-400, oklch(84.1% 0.238 128.85))',
  },
  green: {
    '--chip-light': 'var(--color-green-500, oklch(72.3% 0.219 149.579))',
    '--chip-dark': 'var(--color-green-400, oklch(79.2% 0.209 151.711))',
  },
  emerald: {
    '--chip-light': 'var(--color-emerald-500, oklch(69.6% 0.17 162.48))',
    '--chip-dark': 'var(--color-emerald-400, oklch(76.5% 0.177 163.223))',
  },
  teal: {
    '--chip-light': 'var(--color-teal-500, oklch(70.4% 0.14 182.503))',
    '--chip-dark': 'var(--color-teal-400, oklch(77.7% 0.152 181.912))',
  },
  cyan: {
    '--chip-light': 'var(--color-cyan-500, oklch(71.5% 0.143 215.221))',
    '--chip-dark': 'var(--color-cyan-400, oklch(78.9% 0.154 211.53))',
  },
  sky: {
    '--chip-light': 'var(--color-sky-500, oklch(68.5% 0.169 237.323))',
    '--chip-dark': 'var(--color-sky-400, oklch(74.6% 0.16 232.661))',
  },
  blue: {
    '--chip-light': 'var(--color-blue-500, oklch(62.3% 0.214 259.815))',
    '--chip-dark': 'var(--color-blue-400, oklch(70.7% 0.165 254.624))',
  },
  indigo: {
    '--chip-light': 'var(--color-indigo-500, oklch(58.5% 0.233 277.117))',
    '--chip-dark': 'var(--color-indigo-400, oklch(67.3% 0.182 276.935))',
  },
  violet: {
    '--chip-light': 'var(--color-violet-500, oklch(60.6% 0.25 292.717))',
    '--chip-dark': 'var(--color-violet-400, oklch(70.2% 0.183 293.541))',
  },
  purple: {
    '--chip-light': 'var(--color-purple-500, oklch(62.7% 0.265 303.9))',
    '--chip-dark': 'var(--color-purple-400, oklch(71.4% 0.203 305.504))',
  },
  fuchsia: {
    '--chip-light': 'var(--color-fuchsia-500, oklch(66.7% 0.295 322.15))',
    '--chip-dark': 'var(--color-fuchsia-400, oklch(74% 0.238 322.16))',
  },
  pink: {
    '--chip-light': 'var(--color-pink-500, oklch(65.6% 0.241 354.308))',
    '--chip-dark': 'var(--color-pink-400, oklch(71.8% 0.202 349.761))',
  },
  rose: {
    '--chip-light': 'var(--color-rose-500, oklch(64.5% 0.246 16.439))',
    '--chip-dark': 'var(--color-rose-400, oklch(71.2% 0.194 13.428))',
  },
  slate: {
    '--chip-light': 'var(--color-slate-500, oklch(55.4% 0.046 257.417))',
    '--chip-dark': 'var(--color-slate-400, oklch(70.4% 0.04 256.788))',
  },
  gray: {
    '--chip-light': 'var(--color-gray-500, oklch(55.1% 0.027 264.364))',
    '--chip-dark': 'var(--color-gray-400, oklch(70.7% 0.022 261.325))',
  },
  zinc: {
    '--chip-light': 'var(--color-zinc-500, oklch(55.2% 0.016 285.938))',
    '--chip-dark': 'var(--color-zinc-400, oklch(70.5% 0.015 286.067))',
  },
  neutral: {
    '--chip-light': 'var(--color-neutral-500, oklch(55.6% 0 0))',
    '--chip-dark': 'var(--color-neutral-400, oklch(70.8% 0 0))',
  },
  stone: {
    '--chip-light': 'var(--color-stone-500, oklch(55.3% 0.013 58.071))',
    '--chip-dark': 'var(--color-stone-400, oklch(70.9% 0.01 56.259))',
  },
  taupe: {
    '--chip-light': 'var(--color-taupe-500, oklch(55.7% 0.023 34.45))',
    '--chip-dark': 'var(--color-taupe-400, oklch(70.8% 0.02 34.85))',
  },
  mauve: {
    '--chip-light': 'var(--color-mauve-500, oklch(56.2% 0.033 307.17))',
    '--chip-dark': 'var(--color-mauve-400, oklch(71.4% 0.028 307.09))',
  },
  mist: {
    '--chip-light': 'var(--color-mist-500, oklch(60.1% 0.024 219.12))',
    '--chip-dark': 'var(--color-mist-400, oklch(74.5% 0.021 219.74))',
  },
  olive: {
    '--chip-light': 'var(--color-olive-500, oklch(57.4% 0.043 125.11))',
    '--chip-dark': 'var(--color-olive-400, oklch(72.6% 0.039 125.42))',
  },
} as const satisfies Record<string, Record<'--chip-light' | '--chip-dark', string>>

const props = defineProps<{
  content?: Record<string, unknown>
  items: DropdownMenuItem[][] | DropdownMenuItem[]
  open?: boolean
  ui?: Record<string, unknown>
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

function chipStyle(item: DropdownMenuItem) {
  const colorName = (item as { chip?: keyof typeof chipColorMap }).chip

  return colorName ? chipColorMap[colorName] : null
}
</script>

<template>
  <UDropdownMenu
    :open="props.open"
    :items="props.items"
    :content="props.content"
    :ui="props.ui"
    @update:open="emit('update:open', $event)"
  >
    <slot />

    <template #chip-leading="{ item }">
      <div
        v-if="chipStyle(item)"
        class="inline-flex size-5 shrink-0 items-center justify-center"
      >
        <span
          class="size-2 rounded-full ring ring-bg bg-(--chip-light) dark:bg-(--chip-dark)"
          :style="chipStyle(item)"
        />
      </div>
    </template>
  </UDropdownMenu>
</template>
