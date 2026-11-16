<script setup>
import { computed } from 'vue'

import { cn } from '@/shared/utils/cn'

const props = defineProps({
  type: {
    type: String,
    default: 'button',
  },

  variant: {
    type: String,
    default: 'primary',
  },

  size: {
    type: String,
    default: 'md',
  },

  loading: {
    type: Boolean,
    default: false,
  },

  disabled: {
    type: Boolean,
    default: false,
  },

  full: {
    type: Boolean,
    default: false,
  },
})

const variantClasses = computed(() => {
  const variants = {
    primary:
      'bg-app-primary text-white hover:bg-app-primary/90 focus:ring-app-primary/30',
    secondary:
      'bg-app-surface-strong text-app-text border border-app-border hover:bg-app-surface',
    ghost:
      'text-app-text hover:bg-app-surface',
    danger:
      'bg-app-danger text-white hover:bg-app-danger/90 focus:ring-app-danger/30',
  }

  return variants[props.variant] || variants.primary
})

const sizeClasses = computed(() => {
  const sizes = {
    sm: 'h-9 px-3 text-sm',
    md: 'h-11 px-4 text-sm',
    lg: 'h-12 px-5 text-base',
  }

  return sizes[props.size] || sizes.md
})
</script>

<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    :class="cn(
      'inline-flex items-center justify-center gap-2 rounded-xl font-semibold transition-all duration-200',
      'focus:outline-none focus:ring-4 disabled:cursor-not-allowed disabled:opacity-60',
      variantClasses,
      sizeClasses,
      full && 'w-full'
    )"
  >
    <svg
      v-if="loading"
      class="h-4 w-4 animate-spin"
      viewBox="0 0 24 24"
      fill="none"
    >
      <circle
        class="opacity-25"
        cx="12"
        cy="12"
        r="10"
        stroke="currentColor"
        stroke-width="4"
      />
      <path
        class="opacity-75"
        fill="currentColor"
        d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
      />
    </svg>

    <slot />
  </button>
</template>