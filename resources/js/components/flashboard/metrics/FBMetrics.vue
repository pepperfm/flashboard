<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed } from 'vue'
import type { MetricItem, MetricsPayload } from './types'

const props = defineProps<{
  metrics: MetricsPayload
}>()

const counts = computed(() => props.metrics.items.filter(item => item.kind === 'count'))
const charts = computed(() => props.metrics.items.filter(item => item.kind !== 'count'))
const hasTrends = computed(() => props.metrics.items.some(item => item.kind === 'trend'))
const periods = [7, 30, 90] as const

function changePeriod(days: number) {
  if (days === props.metrics.period_days || typeof window === 'undefined') return

  const url = new URL(window.location.href)
  url.searchParams.set('metric_period', String(days))
  router.get(url.pathname + url.search, {}, { preserveScroll: true, preserveState: false })
}

function countLabel(metric: MetricItem): string {
  return new Intl.NumberFormat().format(metric.value ?? 0)
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

    <div v-if="counts.length" class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <UCard v-for="metric in counts" :key="metric.key" variant="outline">
        <div class="grid gap-1">
          <span class="text-xs text-muted">{{ metric.resource }}</span>
          <span class="text-sm text-default">{{ metric.label }}</span>
          <strong class="text-2xl font-semibold tabular-nums text-highlighted">
            {{ countLabel(metric) }}
          </strong>
        </div>
      </UCard>
    </div>

    <div v-if="charts.length" class="grid grid-cols-1 gap-4 xl:grid-cols-2">
      <UCard v-for="metric in charts" :key="metric.key" variant="outline">
        <template #header>
          <div class="grid gap-1">
            <span class="text-xs text-muted">{{ metric.resource }}</span>
            <h3 class="font-medium text-highlighted">{{ metric.label }}</h3>
          </div>
        </template>

        <UChart
          v-if="metric.points?.length"
          :type="metric.chart"
          :data="metric.points"
          index="label"
          :categories="[{ key: 'value', label: metric.label }]"
          class="h-64 w-full"
        />
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
