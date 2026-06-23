import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import axios from './http/client.js'

window.axios = axios

createInertiaApp({
  resolve: (name) => {
    const pages = import.meta.glob('./Pages/**/*.vue', { eager: true })
    return pages[`./Pages/${name}.vue`]
  },
  setup({ el, App, props, plugin }) {
    return createApp({ render: () => h(App, props) })
      .use(plugin)
      .mount(el)
  },
})
