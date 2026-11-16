import { defineStore } from 'pinia'

const STORAGE_KEY = 'app-theme'

export const useThemeStore = defineStore('theme', {
  state: () => ({
    mode: 'light',
  }),

  getters: {
    isDark: (state) => state.mode === 'dark',
  },

  actions: {
    initialize() {
      const savedTheme = localStorage.getItem(STORAGE_KEY)

      if (savedTheme) {
        this.setTheme(savedTheme)
        return
      }

      const prefersDark = window.matchMedia?.('(prefers-color-scheme: dark)').matches

      this.setTheme(prefersDark ? 'dark' : 'light')
    },

    setTheme(mode) {
      this.mode = mode

      localStorage.setItem(STORAGE_KEY, mode)

      document.documentElement.classList.toggle('dark', mode === 'dark')
    },

    toggleTheme() {
      this.setTheme(this.isDark ? 'light' : 'dark')
    },
  },
})