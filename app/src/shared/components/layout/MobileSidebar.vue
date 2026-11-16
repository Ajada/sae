<script setup>
import { PhX } from '@phosphor-icons/vue'

import AppLogo from '@/shared/components/logo/AppLogo.vue'
import { navigation } from '@/app/config/navigation'

const isOpen = defineModel({
  type: Boolean,
  default: false,
})
</script>

<template>
  <Teleport to="body">
    <div v-if="isOpen" class="fixed inset-0 z-50 lg:hidden">
      <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="isOpen = false" />

      <aside
        class="absolute inset-y-0 left-0 flex w-80 max-w-[85vw] flex-col border-r border-app-border bg-app-surface shadow-2xl"
      >
        <div class="flex h-16 items-center justify-between border-b border-app-border px-5">
          <RouterLink :to="{ name: 'dashboard' }" @click="isOpen = false">
            <AppLogo />
          </RouterLink>

          <button
            type="button"
            class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-app-border bg-app-surface-strong text-app-muted"
            @click="isOpen = false"
          >
            <PhX class="h-5 w-5" />
          </button>
        </div>

        <nav class="flex-1 overflow-y-auto px-4 py-5">
          <div class="space-y-1">
            <template v-for="item in group.items" :key="item.label">
              <div
                v-if="item.disabled"
                class="flex cursor-not-allowed items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-semibold text-app-muted/50"
              >
                <component :is="item.icon" class="h-5 w-5 shrink-0" />

                <span>{{ item.label }}</span>

                <span
                  class="ml-auto rounded-full border border-app-border px-2 py-0.5 text-[10px] font-bold uppercase text-app-muted"
                >
                  Em breve
                </span>
              </div>

              <RouterLink
                v-else
                :to="{ name: item.name }"
                class="flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-semibold text-app-muted transition hover:bg-app-surface-strong hover:text-app-text"
                active-class="!bg-app-primary/10 !text-app-primary"
                @click="isOpen = false"
              >
                <component :is="item.icon" class="h-5 w-5" />

                <span>{{ item.label }}</span>
              </RouterLink>
            </template>
          </div>
        </nav>
      </aside>
    </div>
  </Teleport>
</template>
