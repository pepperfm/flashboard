<script setup lang="ts">
import type { Component } from 'vue'
import { computed, shallowRef, ref, watch } from 'vue'
import { loadPageComponentAssets, type PageComponentAssets } from '@/components/flashboard/custom-pages/pageAssets'

type ActionShape = Record<string, unknown>

type PagePayloadShape = {
  key?: string
  title?: string
  type?: string
  uri?: string
}

type WorkspacePayloadShape = {
  description?: string
  title?: string
}

type PageComponentPayloadShape = {
  assets?: PageComponentAssets
  name: string
  props?: Record<string, unknown>
}

const props = defineProps<{
  actions?: ActionShape[]
  component: PageComponentPayloadShape
  metadata?: Record<string, unknown>
  page?: PagePayloadShape
  workspace?: WorkspacePayloadShape | null
}>()

const renderState = ref<'failed' | 'loading' | 'missing' | 'ready'>('loading')
const resolvedComponent = shallowRef<Component | null>(null)
let loadVersion = 0
const customPageProps = computed(() => ({
  ...(props.component.props ?? {}),
  actions: props.actions ?? [],
  metadata: props.metadata ?? {},
  page: props.page,
  workspace: props.workspace,
}))

watch(
  () => props.component,
  async (componentPayload) => {
    const currentVersion = loadVersion + 1
    loadVersion = currentVersion
    renderState.value = 'loading'
    resolvedComponent.value = null

    try {
      await loadPageComponentAssets(componentPayload.assets)

      if (currentVersion !== loadVersion) {
        return
      }

      const component = window.Flashboard?.resolvePageComponent?.(componentPayload.name)

      if (!component) {
        renderState.value = 'missing'
        return
      }

      resolvedComponent.value = component
      renderState.value = 'ready'
    } catch {
      if (currentVersion === loadVersion) {
        renderState.value = 'failed'
      }
    }
  },
  { deep: true, immediate: true },
)
</script>

<template>
  <component
    :is="resolvedComponent"
    v-if="renderState === 'ready' && resolvedComponent"
    v-bind="customPageProps"
  />

  <UCard v-else-if="renderState === 'loading'" class="custom-page-loading" variant="outline">
    <div class="custom-page-loading__line custom-page-loading__line--title" />
    <div class="custom-page-loading__line" />
    <div class="custom-page-loading__line custom-page-loading__line--short" />
  </UCard>

  <UAlert
    v-else
    color="warning"
    description="The page could not be rendered."
    title="Page unavailable"
    variant="soft"
  />
</template>

<style scoped>
.custom-page-loading {
  min-height: 9rem;
}

.custom-page-loading__line {
  width: min(100%, 32rem);
  height: 0.75rem;
  margin-top: 1rem;
  border-radius: 999px;
  background: color-mix(in srgb, currentColor 12%, transparent);
}

.custom-page-loading__line--title {
  width: min(60%, 18rem);
  height: 1rem;
  margin-top: 0;
}

.custom-page-loading__line--short {
  width: min(42%, 12rem);
}
</style>
