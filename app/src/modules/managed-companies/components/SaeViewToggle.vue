<script setup>
const model = defineModel({ type: String, default: 'table' })
</script>
<template>
  <div class="inline-flex items-center gap-1 rounded-xl border border-app-border bg-app-surface p-1" role="group" aria-label="Modo de visualização">
    <button type="button" class="rounded-lg px-3 py-2 text-xs font-semibold transition" :class="model === 'table' ? 'bg-app-primary-soft text-app-primary' : 'text-app-muted hover:text-app-text'" :aria-pressed="model === 'table'" @click="model = 'table'">Tabela</button>
    <button type="button" class="rounded-lg px-3 py-2 text-xs font-semibold transition" :class="model === 'cards' ? 'bg-app-primary-soft text-app-primary' : 'text-app-muted hover:text-app-text'" :aria-pressed="model === 'cards'" @click="model = 'cards'">Cards</button>
  </div>
</template>
