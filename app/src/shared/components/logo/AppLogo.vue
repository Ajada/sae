<script setup>
import { env } from '@/app/config/env'

defineProps({
  compact: {
    type: Boolean,
    default: false,
  },
})
</script>

<template>
  <div class="flex min-w-0 items-center gap-3">
    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-app-primary text-lg font-black text-white shadow-lg shadow-app-primary/20">
      X
    </div>

    <div
      v-if="!compact"
      class="min-w-0 leading-tight"
    >
      <strong class="block truncate text-base font-black tracking-tight text-app-text">
        {{ env.appName }}
      </strong>

      <span class="block truncate text-xs font-medium text-app-muted">
        Flow platform
      </span>
    </div>
  </div>
</template>