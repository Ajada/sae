<script setup>
import { PhMoon, PhSun } from '@phosphor-icons/vue'

import { useThemeStore } from '@/stores/theme.store'

const themeStore = useThemeStore()
</script>

<template>
  <button
    type="button"
    class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-app-border bg-app-surface-strong text-app-muted transition hover:text-app-text"
    @click="themeStore.toggleTheme()"
  >
    <PhSun
      v-if="themeStore.isDark"
      class="h-5 w-5"
    />

    <PhMoon
      v-else
      class="h-5 w-5"
    />
  </button>
</template>