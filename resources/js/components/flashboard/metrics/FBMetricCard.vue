<script setup lang="ts">
import { computed } from 'vue'
import type { MetricItem } from './types'
import { isScalarMetric, metricValueLabel } from './metricValue'
import { breakdownRows } from './metricsLayout'

const props = defineProps<{
  metric: MetricItem
  compact?: boolean
}>()

const rows = computed(() => breakdownRows(props.metric.points ?? []))
const number = new Intl.NumberFormat()
const percent = new Intl.NumberFormat(undefined, { maximumFractionDigits: 1 })
</script>

<template>
  <UCard variant="outline" class="min-w-0">
    <div v-if="isScalarMetric(metric)" class="grid gap-1">
      <span class="text-xs text-muted">{{ metric.resource }}</span>
      <span class="text-sm text-default">{{ metric.label }}</span>
      <strong class="text-2xl font-semibold tabular-nums text-highlighted break-all">
        {{ metricValueLabel(metric) }}
      </strong>
    </div>

    <template v-if="!isScalarMetric(metric)" #header>
      <div class="grid gap-1">
        <span class="text-xs text-muted">{{ metric.resource }}</span>
        <h3 class="font-medium text-highlighted">{{ metric.label }}</h3>
      </div>
    </template>

    <template v-if="!isScalarMetric(metric)">
      <template v-if="compact && metric.kind === 'breakdown' && rows.length">
        <ul class="grid gap-3">
          <li v-for="(row, index) in rows" :key="index" class="grid gap-1.5">
            <div class="flex items-baseline justify-between gap-3 text-sm">
              <span class="min-w-0 break-words">{{ row.label }}</span>
              <span class="shrink-0 tabular-nums">
                <strong class="font-medium text-highlighted">{{ number.format(row.value) }}</strong>
                <span class="ml-2 text-muted">{{ percent.format(row.share) }}%</span>
              </span>
            </div>
            <div class="h-1.5 overflow-hidden rounded-full bg-elevated" aria-hidden="true">
              <div class="h-full rounded-full bg-primary" :style="{ width: row.share + '%' }" />
            </div>
          </li>
        </ul>
        <p v-if="rows.length >= 10" class="mt-3 text-xs text-muted">
          Percentages refer to the displayed groups.
        </p>
      </template>
      <div v-else-if="metric.points?.length" :class="['min-w-0', compact ? '' : 'pb-4']">
        <UChart
          :type="metric.chart"
          :data="metric.points"
          index="label"
          :categories="[{ key: 'value', label: metric.label }]"
          :class="['min-w-0 w-full', compact ? 'h-48 sm:h-56' : 'h-72']"
        />
      </div>
      <UAlert
        v-else
        color="neutral"
        variant="subtle"
        description="No records for this metric."
      />
    </template>
  </UCard>
</template>
