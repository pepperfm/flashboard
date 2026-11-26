type ActionResponse = {
  successful?: boolean
  message?: string | null
  redirect_to?: string | null
  errors?: Record<string, string[]>
}

export function actionEndpoint(url: string, origin: string): string {
  const resolved = new URL(url, origin)
  if (resolved.origin !== origin) throw new Error('Invalid action endpoint.')
  return resolved.toString()
}

export function actionState(defaults?: Record<string, unknown> | unknown[] | null): Record<string, unknown> {
  return defaults && !Array.isArray(defaults) ? { ...defaults } : {}
}

export function actionOutcome(status: number, result: ActionResponse) {
  if (status === 422) {
    return {
      successful: false as const,
      errors: Object.fromEntries(Object.entries(result.errors ?? {}).map(([name, messages]) => [name.startsWith('payload.') ? name.slice('payload.'.length) : name, messages[0] ?? 'Invalid value.'])),
      message: 'Check the submitted values.',
    }
  }

  if (status < 200 || status >= 300 || result.successful !== true) {
    return {
      successful: false as const,
      errors: {},
      message: status === 409 && result.message ? result.message : 'Action could not be completed.',
    }
  }

  return {
    successful: true as const,
    message: result.message ?? 'Action completed.',
    redirectTo: result.redirect_to,
  }
}
