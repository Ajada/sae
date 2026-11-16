import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import { router } from './router'
import { useThemeStore } from './stores/theme.store'

import './assets/styles/main.css'

const app = createApp(App)

const pinia = createPinia()

app.use(pinia)
app.use(router)

const themeStore = useThemeStore()
themeStore.initialize()

window.addEventListener('auth:unauthenticated', () => {
  const authStore = import('@/modules/auth/stores/auth.store')
  authStore.then(({ useAuthStore }) => {
    useAuthStore().clearSession()
    router.push({ name: 'login' })
  })
})

app.mount('#app')