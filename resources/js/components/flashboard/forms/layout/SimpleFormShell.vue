<script setup lang="ts">
import FormFieldRenderer from '@/components/flashboard/forms/renderers/FormFieldRenderer.vue'
import type { FormFieldShape } from '@/components/flashboard/forms/renderers/resolveFormFieldRenderer'

const props = defineProps<{
  cancelUrl?: string
  errors?: Record<string, string>
  fields: FormFieldShape[]
  mode?: string
  processing?: boolean
  resourceName?: string
  state: Record<string, unknown>
}>()

const emit = defineEmits<{
  submit: []
  'update:field': [fieldKey: string, value: unknown]
  visit: [href: string]
}>()

function fieldError(fieldKey: string): string | undefined {
  const error = props.errors?.[fieldKey]

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
        <div class="simple-field-stack">
          <div
            v-for="field in props.fields"
            :key="field.key"
            class="simple-field-row"
          >
            <FormFieldRenderer
              :field="field"
              :model-value="props.state[field.key]"
              :error="fieldError(field.key)"
              @update:model-value="emit('update:field', field.key, $event)"
            />
          </div>
        </div>

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

.simple-field-row {
  display: grid;
  gap: 0.5rem;
}

.simple-field-stack {
  display: grid;
  gap: 1.25rem;
}
</style>
