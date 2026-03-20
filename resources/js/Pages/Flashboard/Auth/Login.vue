<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import type { FormSubmitEvent } from '@nuxt/ui'
import { computed } from 'vue'

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
</script>

<template>
  <Head :title="`${panelName} Login`" />

  <UApp>
    <div class="login-shell">
      <UAuthForm
        class="login-card"
        icon="i-lucide-user-round"
        :title="panelName"
        description="Sign in to continue into the panel."
        :fields="fields"
        :loading="form.processing"
        :submit="{ label: 'Sign in', block: true, color: 'primary' }"
        @submit="submit"
      >
        <template v-if="error" #validation>
          <UAlert
            color="error"
            variant="soft"
            title="Authentication failed"
            :description="error"
          />
        </template>
      </UAuthForm>
    </div>
  </UApp>
</template>

<style scoped>
.login-shell {
  min-height: 100vh;
  display: grid;
  place-items: center;
  padding: 1rem;
}

.login-card {
  width: min(28rem, 100%);
}

</style>
