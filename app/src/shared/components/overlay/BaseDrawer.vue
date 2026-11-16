<script setup>
import { PhX } from '@phosphor-icons/vue'

defineProps({
  open: {
    type: Boolean,
    default: false,
  },
  title: {
    type: String,
    default: '',
  },
  eyebrow: {
    type: String,
    default: '',
  },
  description: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['close'])

function handleClose() {
  emit('close')
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="drawer-root"
    >
      <button
        type="button"
        class="drawer-backdrop"
        @click="handleClose"
      />

      <aside class="drawer-panel">
        <header class="drawer-header">
          <div>
            <p
              v-if="eyebrow"
              class="drawer-eyebrow"
            >
              {{ eyebrow }}
            </p>

            <h2 class="drawer-title">
              {{ title }}
            </h2>

            <p
              v-if="description"
              class="drawer-description"
            >
              {{ description }}
            </p>
          </div>

          <button
            type="button"
            class="drawer-close"
            @click="handleClose"
          >
            <PhX class="h-5 w-5" />
          </button>
        </header>

        <div class="drawer-body">
          <slot />
        </div>

        <footer
          v-if="$slots.footer"
          class="drawer-footer"
        >
          <slot name="footer" />
        </footer>
      </aside>
    </div>
  </Teleport>
</template>

<style scoped>
.drawer-root {
  position: fixed;
  inset: 0;
  z-index: 80;
  display: flex;
  justify-content: flex-end;
}

.drawer-backdrop {
  position: absolute;
  inset: 0;
  border: 0;
  background: rgb(0 0 0 / 0.68);
}

.drawer-panel {
  position: relative;
  z-index: 1;
  width: min(100vw, 42rem);
  height: 100vh;
  display: flex;
  flex-direction: column;
  background: rgb(var(--color-bg));
  color: rgb(var(--color-text));
  border-left: 1px solid rgb(var(--color-border));
  box-shadow: -24px 0 80px rgb(0 0 0 / 0.35);
}

.drawer-header {
  min-height: 6rem;
  flex-shrink: 0;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  padding: 1.5rem;
  border-bottom: 1px solid rgb(var(--color-border));
  background: rgb(var(--color-surface));
}

.drawer-eyebrow {
  color: rgb(var(--color-primary));
  font-size: 0.75rem;
  line-height: 1rem;
  font-weight: 900;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.drawer-title {
  margin-top: 0.25rem;
  color: rgb(var(--color-text));
  font-size: 1.35rem;
  line-height: 1.75rem;
  font-weight: 900;
}

.drawer-description {
  margin-top: 0.5rem;
  max-width: 34rem;
  color: rgb(var(--color-muted));
  font-size: 0.875rem;
  line-height: 1.5rem;
}

.drawer-close {
  width: 2.5rem;
  height: 2.5rem;
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgb(var(--color-border));
  border-radius: 0.875rem;
  background: rgb(var(--color-bg));
  color: rgb(var(--color-muted));
  transition: color 0.2s ease, background-color 0.2s ease;
}

.drawer-close:hover {
  color: rgb(var(--color-text));
  background: rgb(var(--color-surface-strong));
}

.drawer-body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: 1.5rem;
}

.drawer-footer {
  flex-shrink: 0;
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  padding: 1rem 1.5rem;
  border-top: 1px solid rgb(var(--color-border));
  background: rgb(var(--color-surface));
}

@media (max-width: 640px) {
  .drawer-panel {
    width: 100vw;
  }

  .drawer-header,
  .drawer-body,
  .drawer-footer {
    padding-left: 1rem;
    padding-right: 1rem;
  }
}
</style>