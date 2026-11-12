declare module '*.vue' {
  import type { DefineComponent } from 'vue'

  const component: DefineComponent<Record<string, unknown>, Record<string, unknown>, unknown>

  export default component
}

interface Window {
  Flashboard?: {
    registerPageComponent?: (name: string, component: import('vue').Component) => void
    resolvePageComponent?: (name: string) => import('vue').Component | undefined
  }
}
