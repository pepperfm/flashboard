<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import type { FormSubmitEvent } from '@nuxt/ui'
import { computed, watch } from 'vue'

const props = defineProps<{
  attemptUrl: string
  error?: string | null
  panelName: string
  usernameField: string
  value?: string | null
}>()

const form = useForm({
  [props.usernameField]: props.value ?? '',
  password: '',
  remember: false,
})
const toast = useToast()

const fields = computed(() => [
  {
    name: props.usernameField,
    type: 'text',
    label: props.usernameField,
    required: true,
    defaultValue: props.value ?? '',
    autocomplete: 'username',
  },
  {
    name: 'password',
    type: 'password',
    label: 'Password',
    required: true,
    autocomplete: 'current-password',
  },
  {
    name: 'remember',
    type: 'checkbox',
    label: 'Remember me',
    color: 'neutral',
    defaultValue: false,
  },
])

function submit(event: FormSubmitEvent<Record<string, string | boolean>>) {
  form.transform(() => event.data).post(props.attemptUrl)
}

watch(
  () => props.error,
  (error) => {
    if (!error) {
      return
    }

    toast.add({
      title: 'Authentication failed',
      description: error,
      color: 'error',
      icon: 'i-lucide-circle-alert',
    })
  },
  { immediate: true },
)
</script>

<template>
  <Head :title="`${panelName} Login`" />

  <UApp>
    <div class="login-shell">
      <div class="login-frame">
        <div class="login-intro">
          <p class="login-kicker">{{ panelName }}</p>
          <p class="login-caption">Administrative access</p>
        </div>

        <UPageCard class="login-card" variant="subtle">
          <UAuthForm
            icon="i-lucide-lock"
            title="Sign in"
            description="Enter your credentials to continue."
            :fields="fields"
            :loading="form.processing"
            :submit="{ label: 'Sign in', block: true, color: 'primary' }"
            @submit="submit"
          />
        </UPageCard>
      </div>
    </div>
  </UApp>
</template>

<style scoped>
.login-shell {
  min-height: 100vh;
  background: color-mix(in srgb, var(--ui-bg-muted) 28%, transparent);
}

.login-frame {
  width: 100%;
  max-width: 28rem;
  margin-inline: auto;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 3rem 1rem;
}

.login-intro {
  margin-bottom: 1.5rem;
  text-align: center;
}

.login-kicker {
  margin: 0;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--ui-text-muted);
}

.login-caption {
  margin: 0.5rem 0 0;
  font-size: 0.95rem;
  color: var(--ui-text-muted);
}
</style>
