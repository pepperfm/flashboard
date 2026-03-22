<script setup lang="ts">
import { computed } from 'vue'
import FormFlex from '@/components/flashboard/forms/layout/FormFlex.vue'
import FormGrid from '@/components/flashboard/forms/layout/FormGrid.vue'
import {
  resolveFormContainerLayout,
  resolveFormItemLayout,
  type FormContainerLayoutShape,
} from '@/components/flashboard/forms/layout/resolveFormLayout'
import FormNodeRenderer from '@/components/flashboard/forms/renderers/FormNodeRenderer.vue'
import type { FormNodeShape } from '@/components/flashboard/forms/renderers/resolveFormFieldRenderer'

const props = defineProps<{
  cancelUrl?: string
  errors?: Record<string, string>
  layout?: FormContainerLayoutShape
  mode?: string
  processing?: boolean
  resourceName?: string
  schema: FormNodeShape[]
  state: Record<string, unknown>
}>()

const emit = defineEmits<{
  submit: []
  'update:field': [fieldKey: string, value: unknown]
  visit: [href: string]
}>()

const SIMPLE_FORM_DEFAULT_LAYOUT = {
  gap: 5,
  mode: 'stack',
} as const

const resolvedLayout = computed(() => resolveFormContainerLayout(
  props.layout,
  SIMPLE_FORM_DEFAULT_LAYOUT,
))

function nodeError(node: FormNodeShape): string | undefined {
  if ((node.kind ?? 'field') !== 'field') {
    return undefined
  }

  const error = props.errors?.[node.key]

  return typeof error === 'string' ? error : undefined
}
</script>

<template>
  <UPageSection class="mx-auto w-full max-w-4xl px-2 sm:px-4">
    <UPageCard class="form-card-shell">
      <div class="section-header">
        <div>
          <p class="section-kicker">{{ props.mode === 'edit' ? 'Edit' : 'Create' }}</p>
          <h3 class="section-title">{{ props.resourceName ?? 'Resource' }}</h3>
        </div>
      </div>

      <UForm :state="props.state" class="form-stack" @submit.prevent="emit('submit')">
        <component
          :is="resolvedLayout.mode === 'flex' ? FormFlex : FormGrid"
          :class-name="resolvedLayout.className"
          :style="resolvedLayout.style"
        >
          <div
            v-for="node in props.schema"
            :key="node.key"
            :class="resolveFormItemLayout(resolvedLayout, (node.kind ?? 'field') === 'field' ? node.layout : undefined).className"
            :style="resolveFormItemLayout(resolvedLayout, (node.kind ?? 'field') === 'field' ? node.layout : undefined).style"
          >
            <FormNodeRenderer
              :node="node"
              :state="props.state"
              :errors="props.errors"
              :error="nodeError(node)"
              @update:field="(fieldKey, value) => emit('update:field', fieldKey, value)"
            />
          </div>
        </component>

        <div class="action-row">
          <UButton color="primary" :loading="props.processing" @click="emit('submit')">
            {{ props.mode === 'edit' ? 'Save changes' : 'Create record' }}
          </UButton>

          <UButton
            v-if="props.cancelUrl"
            color="neutral"
            variant="ghost"
            @click="emit('visit', props.cancelUrl)"
          >
            Cancel
          </UButton>
        </div>
      </UForm>
    </UPageCard>
  </UPageSection>
</template>

<style scoped>
.action-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.form-stack {
  display: grid;
  gap: 1rem;
}

.section-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
}

.section-kicker {
  margin: 0 0 0.25rem;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: color-mix(in srgb, var(--ui-text-muted) 92%, transparent);
}

.section-title {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 700;
}

</style>
