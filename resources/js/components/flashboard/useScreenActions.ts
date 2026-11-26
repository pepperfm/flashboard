import { router } from '@inertiajs/vue3'
import { ref } from 'vue'

export type ActionShape = {
  color?: string | null
  form_url?: string
  scope?: string
  modal?: string | null
  modal_description?: string | null
  icon?: string | null
  kind?: string
  key: string
  label?: string
  method?: string
  requires_confirmation?: boolean
  success_message?: string | null
  url?: string
  visible?: boolean
}

type ActionNavigation = Pick<typeof router, 'get' | 'put' | 'patch' | 'delete' | 'post'>

export function useScreenActions(
  navigation: ActionNavigation = router,
  confirmAction: (message: string) => boolean = (message) => window.confirm(message),
) {
  const activeAction = ref<ActionShape | null>(null)

  function runAction(action: ActionShape, confirmed = false) {
    if (!action.url) {
      return
    }

    if (action.form_url || (action.scope && action.scope !== 'legacy' && action.requires_confirmation)) {
      if (!activeAction.value) activeAction.value = action
      return
    }

    if (action.requires_confirmation && !confirmed && !confirmAction('Are you sure you want to continue?')) {
      return
    }

    const method = (action.method ?? 'post').toLowerCase()

    if (method === 'get') {
      navigation.get(action.url, {}, { preserveScroll: true })
      return
    }

    if (method === 'put') {
      navigation.put(action.url, {}, { preserveScroll: true })
      return
    }

    if (method === 'patch') {
      navigation.patch(action.url, {}, { preserveScroll: true })
      return
    }

    if (method === 'delete') {
      navigation.delete(action.url, { preserveScroll: true })
      return
    }

    navigation.post(action.url, {}, { preserveScroll: true })
  }

  return { activeAction, runAction }
}
