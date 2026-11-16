<script setup>
defineProps({
  title: {
    type: String,
    default: '',
  },

  description: {
    type: String,
    default: '',
  },

  padded: {
    type: Boolean,
    default: true,
  },
})
</script>

<template>
  <section class="rounded-3xl border border-app-border bg-app-surface shadow-sm shadow-black/5 dark:shadow-black/20">
    <header
      v-if="title || description || $slots.header || $slots.actions"
      class="flex flex-col gap-4 border-b border-app-border px-5 py-5 sm:flex-row sm:items-center sm:justify-between"
    >
      <slot name="header">
        <div>
          <h2
            v-if="title"
            class="text-lg font-bold text-app-text"
          >
            {{ title }}
          </h2>

          <p
            v-if="description"
            class="mt-1 text-sm leading-6 text-app-muted"
          >
            {{ description }}
          </p>
        </div>
      </slot>

      <div v-if="$slots.actions">
        <slot name="actions" />
      </div>
    </header>

    <div :class="padded ? 'p-5' : ''">
      <slot />
    </div>
  </section>
</template>