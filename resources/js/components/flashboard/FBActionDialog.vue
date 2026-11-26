<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { useToast } from '@nuxt/ui/composables'
import { ref, watch } from 'vue'
import { actionEndpoint, actionOutcome, actionState } from './actionRequests'
import FormNodeRenderer from '@/components/flashboard/forms/renderers/FormNodeRenderer.vue'
import type { FormFieldShape, FormNodeShape } from '@/components/flashboard/forms/renderers/resolveFormFieldRenderer'

type ActionDefinition = {
  key: string
  label?: string
  modal?: string | null
  modal_description?: string | null
  form_url?: string
  url?: string
  requires_confirmation?: boolean
}

type PreparedForm = {
  fields: FormFieldShape[]
  schema?: FormNodeShape[]
  defaults: Record<string, unknown> | unknown[]
}

const props = defineProps<{ action: ActionDefinition | null }>()
const emit = defineEmits<{ close: [] }>()

const toast = useToast()
const schema = ref<FormNodeShape[]>([])
const state = ref<Record<string, unknown>>({})
const errors = ref<Record<string, string>>({})
const message = ref('')
const loading = ref(false)
const pending = ref(false)
const prepared = ref(false)
let key = ''

watch(() => props.action, async (action, _, onCleanup) => {
  const controller = new AbortController()
  onCleanup(() => controller.abort())
  schema.value = []
  state.value = {}
  errors.value = {}
  message.value = ''
  prepared.value = false
  loading.value = false
  if (!action) return

  key = crypto.randomUUID()
  if (!action.form_url) {
    prepared.value = true
    return
  }

  loading.value = true
  try {
    const response = await fetch(actionEndpoint(action.form_url, window.location.origin), {
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      signal: controller.signal,
    })
    if (!response.ok) throw new Error('Action form unavailable.')
    const result = await response.json() as PreparedForm
    if (controller.signal.aborted) return
    schema.value = result.schema ?? result.fields
    state.value = actionState(result.defaults)
    prepared.value = true
  } catch {
    if (!controller.signal.aborted) message.value = 'Action form could not be loaded.'
  } finally {
    if (!controller.signal.aborted) loading.value = false
  }
}, { immediate: true })

function close(): void {
  if (!pending.value) emit('close')
}

async function submit(): Promise<void> {
  const action = props.action
  if (!action?.url || !prepared.value || pending.value) return

  pending.value = true
  errors.value = {}
  message.value = ''
  try {
    const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content
    const response = await fetch(actionEndpoint(action.url, window.location.origin), {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
      },
      body: JSON.stringify({ payload: state.value, idempotency_key: key }),
    })
    const result = actionOutcome(response.status, await response.json())
    if (!result.successful) {
      errors.value = result.errors
      message.value = result.message
      return
    }

    toast.add({ title: result.message, color: 'success' })
    emit('close')
    if (result.redirectTo) {
      const redirect = new URL(result.redirectTo, window.location.origin)
      if (redirect.origin === window.location.origin) {
        router.visit(redirect.toString())
      } else if (['https:', 'http:'].includes(redirect.protocol)) {
        window.location.assign(redirect.toString())
      }
    } else {
      router.reload()
    }
  } catch {
    message.value = 'Action request failed. Retry only if the operation is safe to repeat.'
  } finally {
    pending.value = false
  }
}
</script>

<template>
  <UModal
    :open="!!action"
    :title="action?.modal ?? action?.label ?? action?.key ?? 'Action'"
    :description="action?.modal_description ?? (action?.requires_confirmation ? 'Confirm this action to continue.' : undefined)"
    :dismissible="!pending"
    :close="!pending"
    @update:open="(open: boolean) => { if (!open) close() }"
  >
    <template #body>
      <p v-if="loading" role="status">Loading action…</p>
      <UAlert v-if="message" color="error" variant="subtle" :title="message" class="mb-3" />
      <UForm v-if="prepared" :state="state" :disabled="pending" class="grid gap-4" @submit.prevent="submit">
        <FormNodeRenderer
          v-for="node in schema"
          :key="node.key"
          :node="node"
          :state="state"
          :errors="errors"
          @update:field="(fieldKey, value) => state[fieldKey] = value"
        />
        <div class="flex justify-end gap-2">
          <UButton type="button" color="neutral" variant="ghost" :disabled="pending" @click="close">Cancel</UButton>
          <UButton type="submit" :loading="pending" :disabled="pending">Continue</UButton>
        </div>
      </UForm>
    </template>
  </UModal>
</template>
