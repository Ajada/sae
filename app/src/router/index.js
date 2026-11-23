import { createRouter, createWebHistory } from 'vue-router'
import { routes } from './routes.js'
import { registerRouterGuards } from './guards.js'

export const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior() {
    return {
      top: 0,
    }
  },
})

registerRouterGuards(router)