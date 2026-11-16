<script setup>
import { computed, ref } from 'vue'
import { PhEye, PhEyeSlash } from '@phosphor-icons/vue'

const model = defineModel({
  type: String,
  default: '',
})

const props = defineProps({
  id: {
    type: String,
    default: null,
  },

  label: {
    type: String,
    default: 'Senha',
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
    default: 'current-password',
  },
})

const isVisible = ref(false)

const inputType = computed(() => (isVisible.value ? 'text' : 'password'))
const inputId = computed(() => props.id || `password-${Math.random().toString(36).slice(2)}`)
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

    <div class="relative">
      <input
        :id="inputId"
        v-model="model"
        :type="inputType"
        :placeholder="placeholder"
        :disabled="disabled"
        :autocomplete="autocomplete"
        class="h-11 w-full rounded-xl border border-app-border bg-app-surface-strong px-4 pr-12 text-sm text-app-text outline-none transition placeholder:text-app-muted/70 focus:border-app-primary focus:ring-4 focus:ring-app-primary/10 disabled:cursor-not-allowed disabled:opacity-60"
        :class="error ? 'border-app-danger focus:border-app-danger focus:ring-app-danger/10' : ''"
      >

      <button
        type="button"
        class="absolute right-3 top-1/2 -translate-y-1/2 rounded-lg p-1 text-app-muted transition hover:bg-app-surface hover:text-app-text"
        @click="isVisible = !isVisible"
      >
        <PhEyeSlash
          v-if="isVisible"
          class="h-5 w-5"
        />
        <PhEye
          v-else
          class="h-5 w-5"
        />
      </button>
    </div>

    <p
      v-if="error"
      class="text-sm text-app-danger"
    >
      {{ error }}
    </p>
  </div>
</template>