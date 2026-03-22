<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import FormFieldRenderer from '@/components/flashboard/forms/renderers/FormFieldRenderer.vue'
import type { FormFieldShape } from '@/components/flashboard/forms/renderers/resolveFormFieldRenderer'

type FormGroupShape = {
  description?: string
  icon?: string
  key: string
  label?: string
  schema?: FormFieldShape[]
}

const props = defineProps<{
  cancelUrl?: string
  errors?: Record<string, string>
  mode?: string
  processing?: boolean
  resourceName?: string
  sections: FormGroupShape[]
  standaloneFields: FormFieldShape[]
  state: Record<string, unknown>
  tabs: FormGroupShape[]
}>()

const emit = defineEmits<{
  submit: []
  'update:field': [fieldKey: string, value: unknown]
  visit: [href: string]
}>()

const activeTab = ref<string | number>('')

const tabItems = computed(() =>
  props.tabs.map((tab) => ({
    icon: tab.icon,
    label: tab.label ?? tab.key,
    value: tab.key,
  })),
)

const activeTabSchema = computed(() =>
  props.tabs.find((tab) => tab.key === activeTab.value)?.schema ?? [],
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
        <UTabs
          v-if="tabItems.length"
          v-model="activeTab"
          :items="tabItems"
          :content="false"
          color="neutral"
          variant="pill"
          class="tabs-shell"
        />

        <div v-if="activeTabSchema.length" class="section-stack">
          <UCard
            v-for="field in activeTabSchema"
            :key="field.key"
            variant="soft"
          >
            <FormFieldRenderer
              :field="field"
              :model-value="props.state[field.key]"
              :error="fieldError(field.key)"
              @update:model-value="emit('update:field', field.key, $event)"
            />
          </UCard>
        </div>

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

            <div class="field-grid">
              <div
                v-for="field in section.schema ?? []"
                :key="field.key"
              >
                <FormFieldRenderer
                  :field="field"
                  :model-value="props.state[field.key]"
                  :error="fieldError(field.key)"
                  @update:model-value="emit('update:field', field.key, $event)"
                />
              </div>
            </div>
          </UCard>
        </div>

        <div
          v-if="props.standaloneFields.length"
          class="field-grid"
        >
          <div
            v-for="field in props.standaloneFields"
            :key="field.key"
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

.field-grid {
  display: grid;
  gap: 1rem;
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

.tabs-shell {
  margin-bottom: 0.5rem;
}

@media (min-width: 720px) {
  .field-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
