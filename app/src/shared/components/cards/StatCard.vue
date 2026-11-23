<script setup>
defineProps({
  title: {
    type: String,
    required: true,
  },

  value: {
    type: [String, Number],
    required: true,
  },

  description: {
    type: String,
    default: '',
  },

  icon: {
    type: [Object, Function],
    default: null,
  },

  accent: {
    type: String,
    default: 'primary',
  },
})

const accentClasses = {
  primary: 'bg-app-primary/10 text-app-primary',
  success: 'bg-app-success/10 text-app-success',
  warning: 'bg-app-warning/10 text-app-warning',
  danger: 'bg-app-danger/10 text-app-danger',
}
</script>

<template>
  <article class="rounded-3xl border border-app-border bg-app-surface p-5 shadow-sm shadow-black/5 dark:shadow-black/20">
    <div class="flex items-start justify-between gap-4">
      <div>
        <p class="text-sm font-medium text-app-muted">
          {{ title }}
        </p>

        <p class="mt-3 text-3xl font-black tracking-tight text-app-text">
          {{ value }}
        </p>
      </div>

      <div
        v-if="icon"
        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl"
        :class="accentClasses[accent] || accentClasses.primary"
      >
        <component
          :is="icon"
          class="h-6 w-6"
        />
      </div>
    </div>

    <p
      v-if="description"
      class="mt-4 text-sm leading-6 text-app-muted"
    >
      {{ description }}
    </p>
  </article>
</template>