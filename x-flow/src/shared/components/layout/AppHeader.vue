<script setup>
import { PhList, PhSignOut, PhUserCircle } from '@phosphor-icons/vue'
import { useRouter } from 'vue-router'

import ThemeToggle from '@/shared/components/layout/ThemeToggle.vue'
import { useAuthStore } from '@/modules/auth/stores/auth.store'

defineEmits(['open-sidebar'])

const router = useRouter()
const authStore = useAuthStore()

async function handleLogout() {
  await authStore.logout()

  router.push({ name: 'login' })
}
</script>

<template>
  <header class="app-header">
    <div class="app-header__left">
      <button
        type="button"
        class="app-header__menu-button"
        @click="$emit('open-sidebar')"
      >
        <PhList class="h-5 w-5" />
      </button>

      <div>
        <p class="app-header__eyebrow">
          Plataforma
        </p>

        <h1 class="app-header__title">
          Área interna
        </h1>
      </div>
    </div>

    <div class="app-header__right">
      <ThemeToggle />

      <div class="app-header__user">
        <PhUserCircle class="h-6 w-6 text-app-muted" />

        <div class="min-w-0">
          <p class="app-header__user-name">
            {{ authStore.user?.name || 'Usuário' }}
          </p>

          <p class="app-header__user-company">
            {{ authStore.currentCompany?.name || 'Empresa ativa' }}
          </p>
        </div>
      </div>

      <button
        type="button"
        class="app-header__logout"
        title="Sair"
        @click="handleLogout"
      >
        <PhSignOut class="h-5 w-5" />
      </button>
    </div>
  </header>
</template>

<style scoped>
.app-header {
  position: sticky;
  top: 0;
  z-index: 30;
  height: 4rem;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid rgb(var(--color-border));
  background: rgb(var(--color-bg) / 0.9);
  backdrop-filter: blur(18px);
  padding: 0 1rem;
}

@media (min-width: 640px) {
  .app-header {
    padding-left: 1.5rem;
    padding-right: 1.5rem;
  }
}

@media (min-width: 1024px) {
  .app-header {
    padding-left: 2rem;
    padding-right: 2rem;
  }
}

.app-header__left,
.app-header__right {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.app-header__menu-button {
  width: 2.5rem;
  height: 2.5rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgb(var(--color-border));
  border-radius: 0.75rem;
  background: rgb(var(--color-surface));
  color: rgb(var(--color-muted));
  transition: color 0.2s ease;
}

.app-header__menu-button:hover {
  color: rgb(var(--color-text));
}

@media (min-width: 1024px) {
  .app-header__menu-button {
    display: none;
  }
}

.app-header__eyebrow {
  color: rgb(var(--color-muted));
  font-size: 0.75rem;
  line-height: 1rem;
  font-weight: 900;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.app-header__title {
  color: rgb(var(--color-text));
  font-size: 0.875rem;
  line-height: 1.25rem;
  font-weight: 900;
}

@media (min-width: 640px) {
  .app-header__title {
    font-size: 1rem;
    line-height: 1.5rem;
  }
}

.app-header__user {
  display: none;
}

@media (min-width: 768px) {
  .app-header__user {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    border: 1px solid rgb(var(--color-border));
    border-radius: 1rem;
    background: rgb(var(--color-surface));
    padding: 0.5rem 0.75rem;
  }
}

.app-header__user-name {
  max-width: 10rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: rgb(var(--color-text));
  font-size: 0.875rem;
  line-height: 1.25rem;
  font-weight: 800;
}

.app-header__user-company {
  max-width: 10rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: rgb(var(--color-muted));
  font-size: 0.75rem;
  line-height: 1rem;
}

.app-header__logout {
  width: 2.5rem;
  height: 2.5rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgb(var(--color-border));
  border-radius: 0.75rem;
  background: rgb(var(--color-surface));
  color: rgb(var(--color-muted));
  transition: color 0.2s ease;
}

.app-header__logout:hover {
  color: rgb(var(--color-danger));
}
</style>