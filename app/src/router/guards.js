import { useAuthStore } from '@/modules/auth/stores/auth.store'

export function registerRouterGuards(router) {
  router.beforeEach(async (to) => {
    const authStore = useAuthStore()

    const requiresAuth = to.matched.some((route) => route.meta.requiresAuth)
    const guestOnly = to.matched.some((route) => route.meta.guestOnly)

    const shouldCheckSession = requiresAuth || guestOnly

    if (shouldCheckSession && !authStore.isBootstrapped) {
      try {
        await authStore.fetchUser()
      } catch {
        authStore.clearSession()
      }
    }

    if (requiresAuth && !authStore.isAuthenticated) {
      return {
        name: 'login',
        query: {
          redirect: to.fullPath,
        },
      }
    }

    if (guestOnly && authStore.isAuthenticated) {
      return {
        name: 'dashboard',
      }
    }

    return true
  })
}