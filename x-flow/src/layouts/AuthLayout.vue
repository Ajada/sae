<script setup>
import ThemeToggle from '@/shared/components/layout/ThemeToggle.vue'
import AppLogo from '@/shared/components/logo/AppLogo.vue'
</script>

<template>
  <div class="min-h-screen bg-app-bg text-app-text">
    <div class="fixed right-4 top-4 z-20">
      <ThemeToggle />
    </div>

    <div class="grid min-h-screen grid-cols-1 lg:grid-cols-2">
      <aside class="relative hidden min-h-screen overflow-hidden border-r border-app-border bg-app-surface lg:block">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgb(var(--color-primary)/0.24),transparent_35%),radial-gradient(circle_at_bottom_right,rgb(var(--color-primary)/0.14),transparent_30%)]" />

        <div class="relative z-10 flex min-h-screen flex-col justify-between p-10">
          <AppLogo />

          <div>
            <span class="inline-flex rounded-full border border-app-primary/20 bg-app-primary/10 px-4 py-1.5 text-sm font-semibold text-app-primary">
              Plataforma inteligente
            </span>

            <h1 class="mt-6 max-w-xl text-5xl font-black tracking-tight text-app-text">
              Controle notificações, vencimentos e disparos em um único lugar.
            </h1>

            <p class="mt-6 max-w-lg text-base leading-7 text-app-muted">
              Uma base tecnológica para centralizar comunicações, automatizar tarefas e preparar integrações entre os sistemas da empresa.
            </p>
          </div>

          <div class="rounded-3xl border border-app-border bg-app-bg/70 p-5 backdrop-blur-xl">
            <p class="text-sm font-semibold text-app-text">
              X Flow Core
            </p>

            <p class="mt-2 text-sm leading-6 text-app-muted">
              Sessão segura, filas, agendamentos, logs e estrutura preparada para PWA, offline e múltiplos canais.
            </p>
          </div>
        </div>
      </aside>

      <main class="flex min-h-screen items-center justify-center px-4 py-10 sm:px-6 lg:px-8">
        <RouterView />
      </main>
    </div>
  </div>
</template>