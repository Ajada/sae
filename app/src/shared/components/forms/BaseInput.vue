<script setup>
import { computed } from 'vue'

const model = defineModel({
  type: [String, Number],
  default: '',
})

const props = defineProps({
  id: {
    type: String,
    default: null,
  },

  label: {
    type: String,
    default: '',
  },

  type: {
    type: String,
    default: 'text',
  },

  placeholder: {
    type: String,
    default: '',
  },

  error: {
    type: String,
    default: '',
  },

  disabled: {
    type: Boolean,
    default: false,
  },

  autocomplete: {
    type: String,
    default: 'off',
  },
})

const inputId = computed(() => props.id || `input-${Math.random().toString(36).slice(2)}`)
</script>

<template>
  <div class="space-y-2">
    <label
      v-if="label"
      :for="inputId"
      class="block text-sm font-medium text-app-text"
    >
      {{ label }}
    </label>

    <input
      :id="inputId"
      v-model="model"
      :type="type"
      :placeholder="placeholder"
      :disabled="disabled"
      :autocomplete="autocomplete"
      class="h-11 w-full rounded-xl border border-app-border bg-app-surface-strong px-4 text-sm text-app-text outline-none transition placeholder:text-app-muted/70 focus:border-app-primary focus:ring-4 focus:ring-app-primary/10 disabled:cursor-not-allowed disabled:opacity-60"
      :class="error ? 'border-app-danger focus:border-app-danger focus:ring-app-danger/10' : ''"
    >

    <p
      v-if="error"
      class="text-sm text-app-danger"
    >
      {{ error }}
    </p>
  </div>
</template>