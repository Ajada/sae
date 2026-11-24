<script setup>
import { computed } from 'vue'
const props = defineProps({ person:{type:Object,required:true}, selectedLink:{type:Object,default:null} })
const emit = defineEmits(['select','open-asos','registration','toggle-away','terminate'])
const keyOf = item => item.uuid || item.id
const links = computed(() => [...(props.person.links || [])].sort((a,b) => Number(Boolean(a.terminated_at))-Number(Boolean(b.terminated_at)) || String(b.admitted_at ?? '').localeCompare(String(a.admitted_at ?? ''))))
const date = d => d ? String(d).slice(0,10).split('-').reverse().join('/') : '—'
const situation = l => l.terminated_at ? 'Desligado' : l.is_away ? 'Afastado' : 'Ativo'
</script>
<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3"><div><h4 class="font-bold text-app-text">Histórico de vínculos</h4><p class="text-xs text-app-muted">Selecione um vínculo para editar seus dados. A gestão de exames fica no módulo ASOs.</p></div><span class="text-xs text-app-muted">{{ links.length }} vínculo(s)</span></div>
    <div class="flex gap-2 overflow-x-auto pb-1" role="group" aria-label="Selecionar vínculo">
      <button v-for="(link,index) in links" :key="keyOf(link)" type="button" class="min-w-36 shrink-0 rounded-xl border p-3 text-left shadow-sm transition" :class="selectedLink && keyOf(selectedLink) === keyOf(link) ? 'border-app-primary bg-app-primary-soft text-app-primary' : 'border-app-border bg-app-bg text-app-text hover:border-app-primary'" :aria-pressed="!!selectedLink && keyOf(selectedLink) === keyOf(link)" @click="emit('select', link)">
        <span class="block text-xs font-bold">Vínculo {{ index+1 }}</span><span class="mt-1 block truncate text-xs">{{ link.registration || 'Sem matrícula' }}</span><span class="mt-1 block text-xs opacity-80">{{ situation(link) }}</span>
      </button>
    </div>
    <div v-if="selectedLink" class="rounded-xl border border-app-border bg-app-surface-strong p-4">
      <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="font-bold text-app-text">{{ selectedLink.job_title || 'Função não informada' }}</p><p class="mt-1 text-xs text-app-muted">Matrícula: {{ selectedLink.registration || 'Não informada' }}</p><p class="mt-1 text-xs text-app-muted">{{ date(selectedLink.admitted_at) }} — {{ selectedLink.terminated_at ? date(selectedLink.terminated_at) : 'Atual' }} · {{ situation(selectedLink) }}</p></div><div class="flex flex-wrap gap-2"><button type="button" class="link-action link-action-primary" @click="emit('open-asos', selectedLink)">Abrir em ASOs ↗</button><button class="link-action" @click="emit('registration',selectedLink)">Matrícula</button><button v-if="!selectedLink.terminated_at" class="link-action" @click="emit('toggle-away',selectedLink)">{{ selectedLink.is_away ? 'Reabilitar' : 'Afastar' }}</button><button v-if="!selectedLink.terminated_at" class="link-action" @click="emit('terminate',selectedLink)">Desligar</button></div></div>
      <div class="mt-4 rounded-xl border border-app-border bg-app-primary-soft/30 p-3 text-xs text-app-muted">
        Histórico de exames e novos lançamentos estão disponíveis em <strong class="text-app-primary">ASOs</strong>, no vínculo selecionado.
      </div>
    </div>
  </div>
</template>
<style scoped>
.link-action {border:1px solid rgb(var(--color-border));border-radius:.65rem;padding:.5rem .7rem;font-size:.75rem;font-weight:600;color:rgb(var(--color-text));background:rgb(var(--color-surface));}
.link-action:hover {border-color:rgb(var(--color-primary));color:rgb(var(--color-primary));}
</style>

<style scoped>.link-action-primary {color:rgb(var(--color-primary));border-color:rgb(var(--color-primary) / .35);background:rgb(var(--color-primary-soft) / .55);}</style>
