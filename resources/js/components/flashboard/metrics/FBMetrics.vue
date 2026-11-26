<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed } from 'vue'
import type { MetricItem, MetricsPayload } from './types'
import { metricValueLabel, isScalarMetric } from './metricValue'

const props = defineProps<{
  metrics: MetricsPayload
}>()

const scalars = computed(() => props.metrics.items.filter(isScalarMetric))
const charts = computed(() => props.metrics.items.filter(item => !isScalarMetric(item)))
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
  <section v-if="metrics.items.length" class="grid gap-4" aria-label="Resource metrics">
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

    <div v-if="scalars.length" class="grid grid-cols-1 gap-3 xl:grid-cols-12">
      <UCard v-for="metric in scalars" :key="metric.key" :class="['min-w-0', spanClass(metric)]" variant="outline">
        <div class="grid gap-1">
          <span class="text-xs text-muted">{{ metric.resource }}</span>
          <span class="text-sm text-default">{{ metric.label }}</span>
          <strong class="text-2xl font-semibold tabular-nums text-highlighted break-all">
            {{ metricValueLabel(metric) }}
          </strong>
        </div>
      </UCard>
    </div>

    <div v-if="charts.length" class="grid grid-cols-1 gap-4 xl:grid-cols-12">
      <UCard v-for="metric in charts" :key="metric.key" :class="['min-w-0', spanClass(metric)]" variant="outline">
        <template #header>
          <div class="grid gap-1">
            <span class="text-xs text-muted">{{ metric.resource }}</span>
            <h3 class="font-medium text-highlighted">{{ metric.label }}</h3>
          </div>
        </template>

        <div v-if="metric.points?.length" class="min-w-0 pb-4">
          <UChart
            :type="metric.chart"
            :data="metric.points"
            index="label"
            :categories="[{ key: 'value', label: metric.label }]"
            class="h-72 min-w-0 w-full"
          />
        </div>
        <UAlert
          v-else
          color="neutral"
          variant="subtle"
          description="No records for this metric."
        />
      </UCard>
    </div>
  </section>
</template>
