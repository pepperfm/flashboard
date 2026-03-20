import '../css/flashboard.css'

import { createInertiaApp } from '@inertiajs/vue3'
import ui from '@nuxt/ui/vue-plugin'
import { createApp, h, type DefineComponent } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'

createInertiaApp({
  progress: {
    color: '#2d241c',
  },
  resolve: (name) => {
    const pages = import.meta.glob('./Pages/**/*.vue')
    const page = pages[`./Pages/${name}.vue`]

    if (!page) {
      throw new Error(`Inertia page not found: ${name}`)
    }

    return page() as Promise<DefineComponent>
  },
  setup({ el, App, props, plugin }) {
    const router = createRouter({
      history: createWebHistory(),
      routes: [],
    })

    createApp({ render: () => h(App, props) })
      .use(router)
      .use(plugin)
      .use(ui)
      .mount(el)
  },
  title: (title) => (title ? `${title} · Flashboard` : 'Flashboard'),
})
