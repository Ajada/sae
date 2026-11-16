import { defineStore } from 'pinia'

import { authService } from '../services/auth.service'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    companyId: null,
    isLoading: false,
    isBootstrapped: false,
  }),

  getters: {
    isAuthenticated: (state) => Boolean(state.user),
    currentCompany: (state) => state.user?.current_company || null,
  },

  actions: {
    async login(credentials) {
      this.isLoading = true

      try {
        const data = await authService.login(credentials)

        this.user = data.user
        this.companyId = data.user?.current_company_id || data.company_id || null

        return data
      } finally {
        this.isLoading = false
      }
    },

    async fetchUser() {
      try {
        const data = await authService.me()

        this.user = data.user
        this.companyId = data.company_id || data.user?.current_company_id || null

        return data
      } catch (error) {
        this.user = null
        this.companyId = null

        throw error
      } finally {
        this.isBootstrapped = true
      }
    },

    async logout() {
      try {
        await authService.logout()
      } finally {
        this.user = null
        this.companyId = null
        this.isBootstrapped = true
      }
    },

    clearSession() {
      this.user = null
      this.companyId = null
      this.isBootstrapped = true
    },
  },
})