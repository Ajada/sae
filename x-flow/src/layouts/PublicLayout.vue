<script setup>
import ThemeToggle from '@/shared/components/layout/ThemeToggle.vue'
import AppLogo from '@/shared/components/logo/AppLogo.vue'
</script>

<template>
  <div class="min-h-screen bg-app-bg text-app-text">
    <header class="border-b border-app-border bg-app-bg/80 backdrop-blur-xl">
      <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <RouterLink :to="{ name: 'landing' }">
          <AppLogo />
        </RouterLink>

        <div class="flex items-center gap-3">
          <ThemeToggle />

          <RouterLink
            :to="{ name: 'login' }"
            class="rounded-xl bg-app-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-app-primary/90"
          >
            Entrar
          </RouterLink>
        </div>
      </div>
    </header>

    <main>
      <RouterView />
    </main>
  </div>
</template>