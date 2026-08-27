import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { setUnauthenticatedHandler } from './api/client'
import { useAuthStore } from './stores/auth'
import './assets/styles.css'

const app = createApp(App)
app.use(createPinia())
app.use(router)

// A dropped session sends the person to sign-in once, from anywhere.
setUnauthenticatedHandler(() => {
  const auth = useAuthStore()
  auth.clear()
  if (router.currentRoute.value.name !== 'sign-in') {
    router.push({ name: 'sign-in', query: { next: router.currentRoute.value.fullPath } })
  }
})

app.mount('#app')
