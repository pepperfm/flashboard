<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed } from 'vue'
import type { MetricItem, MetricsPayload } from './types'
import { isScalarMetric } from './metricValue'
import FBMetricCard from './FBMetricCard.vue'
import { dashboardGroups, type MetricsMode } from './metricsLayout'

const props = withDefaults(defineProps<{
  metrics: MetricsPayload
  mode?: MetricsMode
}>(), { mode: 'resource' })

const groups = computed(() => dashboardGroups(props.metrics.items))
const dashboardGrid = {
  summary: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6',
  trends: 'grid-cols-1 lg:grid-cols-2',
  breakdowns: 'grid-cols-1 md:grid-cols-2 2xl:grid-cols-3',
} as const

const hasTrends = computed(() => props.metrics.items.some(item => item.kind === 'trend'))
// Static class names are intentional so Tailwind includes all supported spans.
const spanClasses = [
  '',
  'xl:col-span-1',
  'xl:col-span-2',
  'xl:col-span-3',
  'xl:col-span-4',
  'xl:col-span-5',
  'xl:col-span-6',
  'xl:col-span-7',
  'xl:col-span-8',
  'xl:col-span-9',
  'xl:col-span-10',
  'xl:col-span-11',
  'xl:col-span-12',
] as const

function spanClass(metric: MetricItem): string {
  const fallback = isScalarMetric(metric) ? 3 : 6
  const span = metric.span

  return spanClasses[typeof span === 'number' && Number.isInteger(span) && span >= 1 && span <= 12 ? span : fallback]!
}

const periods = [7, 30, 90] as const

function changePeriod(days: number) {
  if (days === props.metrics.period_days || typeof window === 'undefined') return

  const url = new URL(window.location.href)
  url.searchParams.set('metric_period', String(days))
  router.get(url.pathname + url.search, {}, { preserveScroll: true, preserveState: false })
}

</script>

<template>
  <section v-if="metrics.items.length" class="grid gap-4" :aria-label="mode === 'dashboard' ? 'Dashboard metrics' : 'Resource metrics'">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h2 class="text-base font-semibold text-highlighted">Metrics</h2>
      <div v-if="hasTrends" class="flex items-center gap-1" aria-label="Metrics time range">
        <UButton
          v-for="days in periods"
          :key="days"
          :aria-pressed="metrics.period_days === days"
          color="neutral"
          size="xs"
          :variant="metrics.period_days === days ? 'soft' : 'ghost'"
          @click="changePeriod(days)"
        >
          {{ days }} days
        </UButton>
      </div>
    </div>

    <template v-if="mode === 'dashboard'">
      <section v-for="group in groups" :key="group.key" class="grid gap-3">
        <h3 class="text-sm font-medium text-muted">{{ group.label }}</h3>
        <div :class="['grid gap-3', dashboardGrid[group.key]]">
          <FBMetricCard v-for="metric in group.items" :key="metric.key" :metric="metric" compact />
        </div>
      </section>
    </template>

    <div v-else class="grid grid-cols-1 items-start gap-4 xl:grid-cols-12">
      <FBMetricCard v-for="metric in metrics.items" :key="metric.key" :class="spanClass(metric)" :metric="metric" />
    </div>
  </section>
</template>
