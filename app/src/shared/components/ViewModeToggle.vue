<script setup>
import { computed } from 'vue'
const props = defineProps({ modelValue: { type: String, default: 'table' } })
const emit = defineEmits(['update:modelValue'])
const options = [{ value: 'table', label: 'Tabela' }, { value: 'cards', label: 'Cards' }]
const selected = computed({ get: () => props.modelValue, set: value => emit('update:modelValue', value) })
</script>
<template>
  <div class="inline-flex rounded-lg border border-app-border p-1" role="group" aria-label="Modo de visualização">
    <button v-for="option in options" :key="option.value" type="button"
      :aria-pressed="selected === option.value" :class="[
        'rounded-md px-3 py-2 text-sm transition-colors',
        selected === option.value ? 'bg-app-surface font-semibold text-app-text ring-1 ring-app-border' : 'text-app-muted hover:text-app-text'
      ]" @click="selected = option.value">
      {{ option.label }}
    </button>
  </div>
</template>
