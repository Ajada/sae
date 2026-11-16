<script setup>
import AppLogo from '@/shared/components/logo/AppLogo.vue'
import { navigation } from '@/app/config/navigation'
</script>

<template>
  <aside class="app-sidebar">
    <div class="app-sidebar__brand">
      <RouterLink :to="{ name: 'dashboard' }">
        <AppLogo />
      </RouterLink>
    </div>

    <nav class="app-sidebar__nav">
      <div
        v-for="group in navigation"
        :key="group.label"
        class="app-sidebar__group"
      >
        <p class="app-sidebar__group-title">
          {{ group.label }}
        </p>

        <div class="app-sidebar__items">
          <template
            v-for="item in group.items"
            :key="item.label"
          >
            <div
              v-if="item.disabled"
              class="app-sidebar__link app-sidebar__link--disabled"
            >
              <component
                :is="item.icon"
                class="app-sidebar__icon"
              />

              <span class="app-sidebar__label">
                {{ item.label }}
              </span>

              <span class="app-sidebar__badge">
                Em breve
              </span>
            </div>

            <RouterLink
              v-else
              :to="{ name: item.name }"
              class="app-sidebar__link"
              active-class="app-sidebar__link--active"
            >
              <component
                :is="item.icon"
                class="app-sidebar__icon"
              />

              <span class="app-sidebar__label">
                {{ item.label }}
              </span>
            </RouterLink>
          </template>
        </div>
      </div>
    </nav>

    <div class="app-sidebar__footer">
      <div class="app-sidebar__card">
        <p class="app-sidebar__card-title">
          Base App
        </p>

        <p class="app-sidebar__card-text">
          Estrutura reutilizável com autenticação, temas, rotas e layout modular.
        </p>
      </div>
    </div>
  </aside>
</template>

<style scoped>
.app-sidebar {
  display: none;
}

@media (min-width: 1024px) {
  .app-sidebar {
    position: fixed;
    inset: 0 auto 0 0;
    z-index: 40;
    width: 18rem;
    height: 100vh;
    display: flex;
    flex-direction: column;
    background: rgb(var(--color-surface));
    border-right: 1px solid rgb(var(--color-border));
  }
}

.app-sidebar__brand {
  height: 4rem;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  padding: 0 1.5rem;
  border-bottom: 1px solid rgb(var(--color-border));
}

.app-sidebar__nav {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: 1.25rem 1rem;
}

.app-sidebar__group {
  margin-bottom: 1.75rem;
}

.app-sidebar__group-title {
  margin-bottom: 0.5rem;
  padding: 0 0.75rem;
  font-size: 0.75rem;
  line-height: 1rem;
  font-weight: 900;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: rgb(var(--color-muted));
}

.app-sidebar__items {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.app-sidebar__link {
  min-height: 2.75rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  border-radius: 1rem;
  padding: 0.625rem 0.75rem;
  color: rgb(var(--color-muted));
  font-size: 0.875rem;
  line-height: 1.25rem;
  font-weight: 700;
  text-decoration: none;
  transition:
    background-color 0.2s ease,
    color 0.2s ease;
}

.app-sidebar__link:hover {
  background: rgb(var(--color-surface-strong));
  color: rgb(var(--color-text));
}

.app-sidebar__link--active {
  background: rgb(var(--color-primary) / 0.1);
  color: rgb(var(--color-primary));
}

.app-sidebar__link--disabled {
  cursor: not-allowed;
  color: rgb(var(--color-muted) / 0.5);
}

.app-sidebar__link--disabled:hover {
  background: transparent;
  color: rgb(var(--color-muted) / 0.5);
}

.app-sidebar__icon {
  width: 1.25rem;
  height: 1.25rem;
  flex-shrink: 0;
}

.app-sidebar__label {
  min-width: 0;
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.app-sidebar__badge {
  flex-shrink: 0;
  border: 1px solid rgb(var(--color-border));
  border-radius: 999px;
  padding: 0.125rem 0.5rem;
  color: rgb(var(--color-muted));
  font-size: 0.625rem;
  line-height: 1rem;
  font-weight: 900;
  text-transform: uppercase;
}

.app-sidebar__footer {
  flex-shrink: 0;
  padding: 1rem;
  border-top: 1px solid rgb(var(--color-border));
}

.app-sidebar__card {
  border: 1px solid rgb(var(--color-border));
  border-radius: 1rem;
  background: rgb(var(--color-bg));
  padding: 1rem;
}

.app-sidebar__card-title {
  color: rgb(var(--color-text));
  font-size: 0.875rem;
  line-height: 1.25rem;
  font-weight: 800;
}

.app-sidebar__card-text {
  margin-top: 0.25rem;
  color: rgb(var(--color-muted));
  font-size: 0.75rem;
  line-height: 1.25rem;
}
</style>