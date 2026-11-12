import type { Component } from 'vue'

export type FlashboardPageComponentRegistry = {
  registerPageComponent: (name: string, component: Component) => void
  resolvePageComponent: (name: string) => Component | undefined
}

const pageComponents = new Map<string, Component>()

export function normalizePageComponentName(name: string): string {
  return name.trim()
}

export function registerPageComponent(name: string, component: Component): void {
  const normalizedName = normalizePageComponentName(name)

  if (normalizedName === '') {
    throw new Error('Flashboard page component name is required.')
  }

  pageComponents.set(normalizedName, component)
}

export function resolvePageComponent(name: string): Component | undefined {
  return pageComponents.get(normalizePageComponentName(name))
}

export function installFlashboardRuntime(): FlashboardPageComponentRegistry | undefined {
  if (typeof window === 'undefined') {
    return undefined
  }

  const runtime = {
    registerPageComponent,
    resolvePageComponent,
  }

  window.Flashboard = {
    ...window.Flashboard,
    ...runtime,
  }

  return runtime
}
