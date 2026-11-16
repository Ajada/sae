<script setup>
import { ref } from 'vue'

import AppHeader from '@/shared/components/layout/AppHeader.vue'
import MobileSidebar from '@/shared/components/layout/MobileSidebar.vue'
import AppSidebar from '@/shared/components/layout/AppSidebar.vue'

const isMobileSidebarOpen = ref(false)
</script>

<template>
  <div class="app-shell">
    <AppSidebar />

    <MobileSidebar v-model="isMobileSidebarOpen" />

    <div class="app-shell__main">
      <AppHeader @open-sidebar="isMobileSidebarOpen = true" />

      <main class="app-shell__content">
        <div class="app-shell__container">
          <RouterView />
        </div>
      </main>
    </div>
  </div>
</template>

<style scoped>
.app-shell {
  min-height: 100vh;
  background: rgb(var(--color-bg));
  color: rgb(var(--color-text));
}

.app-shell__main {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.app-shell__content {
  flex: 1;
  width: 100%;
  overflow-x: hidden;
  padding: 1.5rem 1rem;
}

.app-shell__container {
  width: 100%;
  max-width: 80rem;
  margin: 0 auto;
}

@media (min-width: 640px) {
  .app-shell__content {
    padding-left: 1.5rem;
    padding-right: 1.5rem;
  }
}

@media (min-width: 1024px) {
  .app-shell__main {
    padding-left: 18rem;
  }

  .app-shell__content {
    padding-left: 2rem;
    padding-right: 2rem;
  }
}
</style>