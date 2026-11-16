<script setup>
import LoginForm from '../components/LoginForm.vue'
import AppLogo from '@/shared/components/logo/AppLogo.vue'
</script>

<template>
  <section class="w-full max-w-md">
    <div class="mb-8 flex justify-center lg:hidden">
      <AppLogo />
    </div>

    <div class="rounded-3xl border border-app-border bg-app-surface p-6 shadow-xl shadow-black/5 dark:shadow-black/20 sm:p-8">
      <div class="mb-8">
        <p class="text-sm font-semibold text-app-primary">
          Bem-vindo de volta
        </p>

        <h1 class="mt-2 text-3xl font-black tracking-tight text-app-text">
          Acesse sua conta
        </h1>

        <p class="mt-3 text-sm leading-6 text-app-muted">
          Entre para gerenciar notificações, disparos, vencimentos e automações.
        </p>
      </div>

      <LoginForm />

      <p class="mt-8 text-center text-xs leading-5 text-app-muted">
        Ao continuar, você acessa um ambiente protegido e monitorado pelos logs do sistema.
      </p>
    </div>
  </section>
</template>