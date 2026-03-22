<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import FormFlex from '@/components/flashboard/forms/layout/FormFlex.vue'
import FormGrid from '@/components/flashboard/forms/layout/FormGrid.vue'
import {
  resolveFormContainerLayout,
  resolveFormItemLayout,
} from '@/components/flashboard/forms/layout/resolveFormLayout'
import type {
  FormNodeShape,
  FormSectionShape,
  FormTabShape,
  FormTabsShape,
} from '@/components/flashboard/forms/renderers/resolveFormFieldRenderer'
import FormNodeRenderer from '@/components/flashboard/forms/renderers/FormNodeRenderer.vue'

const props = defineProps<{
  errors?: Record<string, string>
  node: FormSectionShape | FormTabShape | FormTabsShape
  state: Record<string, unknown>
}>()

const emit = defineEmits<{
  'update:field': [fieldKey: string, value: unknown]
}>()

const activeTab = ref<string | number>('')

const containerDefaults = computed(() => {
  if (props.node.kind === 'section' || props.node.kind === 'tab') {
    return {
      columns: 2,
      gap: 4,
      mode: 'grid',
    } as const
  }

  return {
    gap: 4,
    mode: 'stack',
  } as const
})

const resolvedLayout = computed(() => resolveFormContainerLayout(
  props.node.kind === 'tabs' ? undefined : props.node.layout,
  containerDefaults.value,
))
const tabs = computed(() => props.node.kind === 'tabs' ? props.node.tabs ?? [] : [])
const tabItems = computed(() => tabs.value.map((tab) => ({
  icon: tab.icon,
  label: tab.label ?? tab.key,
  value: tab.key,
})))
const activeTabNode = computed(() =>
  tabs.value.find((tab) => tab.key === activeTab.value),
)

watch(
  () => tabItems.value,
  (items) => {
    const hasActive = items.some((item) => item.value === activeTab.value)

    if (!hasActive) {
      activeTab.value = items[0]?.value ?? ''
    }
  },
  { immediate: true },
)

const childNodes = computed<FormNodeShape[]>(() => {
  if (props.node.kind === 'tabs') {
    return []
  }

  return props.node.schema ?? []
})

function nodeError(node: FormNodeShape): string | undefined {
  if ((node.kind ?? 'field') !== 'field') {
    return undefined
  }

  const error = props.errors?.[node.key]

  return typeof error === 'string' ? error : undefined
}
</script>

<template>
  <template v-if="props.node.kind === 'tabs'">
    <div class="fb-form-tabs">
      <UTabs
        v-if="tabItems.length"
        v-model="activeTab"
        :items="tabItems"
        :content="false"
        color="neutral"
        variant="pill"
        class="fb-form-tabs__nav"
      />

      <UCard v-if="activeTabNode" variant="soft">
        <template v-if="activeTabNode.label || activeTabNode.icon" #header>
          <div>
            <h4 class="fb-form-container__title">{{ activeTabNode.label ?? activeTabNode.key }}</h4>
          </div>
        </template>

        <FormContainerRenderer
          :node="activeTabNode"
          :errors="props.errors"
          :state="props.state"
          @update:field="(fieldKey, value) => emit('update:field', fieldKey, value)"
        />
      </UCard>
    </div>
  </template>

  <UCard v-else-if="props.node.kind === 'section'" variant="soft">
    <template v-if="props.node.label || props.node.description" #header>
      <div>
        <h4 class="fb-form-container__title">{{ props.node.label ?? props.node.key }}</h4>
        <p v-if="props.node.description" class="fb-form-container__description">
          {{ props.node.description }}
        </p>
      </div>
    </template>

    <component
      :is="resolvedLayout.mode === 'flex' ? FormFlex : FormGrid"
      :class-name="resolvedLayout.className"
      :style="resolvedLayout.style"
    >
      <div
        v-for="child in childNodes"
        :key="child.key"
        :class="resolveFormItemLayout(resolvedLayout, child.kind === 'field' ? child.layout : undefined).className"
        :style="resolveFormItemLayout(resolvedLayout, child.kind === 'field' ? child.layout : undefined).style"
      >
        <FormNodeRenderer
          :node="child"
          :state="props.state"
          :errors="props.errors"
          :error="nodeError(child)"
          @update:field="(fieldKey, value) => emit('update:field', fieldKey, value)"
        />
      </div>
    </component>
  </UCard>

  <component
    :is="resolvedLayout.mode === 'flex' ? FormFlex : FormGrid"
    v-else
    :class-name="resolvedLayout.className"
    :style="resolvedLayout.style"
  >
    <div
      v-for="child in childNodes"
      :key="child.key"
      :class="resolveFormItemLayout(resolvedLayout, (child.kind ?? 'field') === 'field' ? child.layout : undefined).className"
      :style="resolveFormItemLayout(resolvedLayout, (child.kind ?? 'field') === 'field' ? child.layout : undefined).style"
    >
      <FormNodeRenderer
        :node="child"
        :state="props.state"
        :errors="props.errors"
        :error="nodeError(child)"
        @update:field="(fieldKey, value) => emit('update:field', fieldKey, value)"
      />
    </div>
  </component>
</template>

<style scoped>
.fb-form-container__description {
  margin: 0.35rem 0 0;
  color: color-mix(in srgb, var(--ui-text-muted) 92%, transparent);
}

.fb-form-container__title {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
}

.fb-form-tabs {
  display: grid;
  gap: 1rem;
}

.fb-form-tabs__nav {
  margin-bottom: 0.5rem;
}
</style>
