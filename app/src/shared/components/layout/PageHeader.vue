<script setup>
defineProps({
  eyebrow: {
    type: String,
    default: '',
  },

  title: {
    type: String,
    required: true,
  },

  description: {
    type: String,
    default: '',
  },
})
</script>

<template>
  <header class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
      <p
        v-if="eyebrow"
        class="text-sm font-bold uppercase tracking-wider text-app-primary"
      >
        {{ eyebrow }}
      </p>

      <h1 class="mt-2 text-3xl font-black tracking-tight text-app-text sm:text-4xl">
        {{ title }}
      </h1>

      <p
        v-if="description"
        class="mt-3 max-w-3xl text-sm leading-6 text-app-muted sm:text-base"
      >
        {{ description }}
      </p>
    </div>

    <div v-if="$slots.actions">
      <slot name="actions" />
    </div>
  </header>
</template>