<script setup lang="ts">
import FormFieldsLayout from '@/components/flashboard/forms/layout/FormFieldsLayout.vue'
import type { FormContainerLayoutShape } from '@/components/flashboard/forms/layout/resolveFormLayout'
import type { FormFieldShape } from '@/components/flashboard/forms/renderers/resolveFormFieldRenderer'

type FormGroupShape = {
  description?: string
  key: string
  label?: string
  layout?: FormContainerLayoutShape
  schema?: FormFieldShape[]
}

const props = defineProps<{
  cancelUrl?: string
  errors?: Record<string, string>
  layout?: FormContainerLayoutShape
  mode?: string
  processing?: boolean
  resourceName?: string
  sections: FormGroupShape[]
  standaloneFields: FormFieldShape[]
  state: Record<string, unknown>
}>()

const emit = defineEmits<{
  submit: []
  'update:field': [fieldKey: string, value: unknown]
  visit: [href: string]
}>()

const GROUPED_FORM_DEFAULT_LAYOUT = {
  columns: 2,
  gap: 4,
  mode: 'grid',
} as const
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
        <div
          v-for="section in props.sections"
          :key="section.key"
          class="section-stack"
        >
          <UCard variant="soft">
            <template #header>
              <div>
                <h4 class="subsection-title">{{ section.label ?? section.key }}</h4>
                <p v-if="section.description" class="subsection-description">
                  {{ section.description }}
                </p>
              </div>
            </template>

            <FormFieldsLayout
              :fields="section.schema ?? []"
              :layout="section.layout"
              :default-layout="GROUPED_FORM_DEFAULT_LAYOUT"
              :errors="props.errors"
              :state="props.state"
              @update:field="(fieldKey, value) => emit('update:field', fieldKey, value)"
            />
          </UCard>
        </div>

        <FormFieldsLayout
          v-if="props.standaloneFields.length"
          :fields="props.standaloneFields"
          :layout="props.layout"
          :default-layout="GROUPED_FORM_DEFAULT_LAYOUT"
          :errors="props.errors"
          :state="props.state"
          @update:field="(fieldKey, value) => emit('update:field', fieldKey, value)"
        />

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

.section-stack {
  display: grid;
  gap: 1rem;
}

.section-title {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 700;
}

.subsection-description {
  margin: 0.35rem 0 0;
  color: color-mix(in srgb, var(--ui-text-muted) 92%, transparent);
}

.subsection-title {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
}

</style>
