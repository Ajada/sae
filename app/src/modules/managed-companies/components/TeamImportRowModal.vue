<script setup>
import { computed, reactive, watch, onMounted, onBeforeUnmount } from 'vue'
import { PhX, PhUser, PhIdentificationCard, PhClipboardText, PhWarningCircle } from '@phosphor-icons/vue'
import BaseButton from '@/shared/components/buttons/BaseButton.vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  row: { type: Object, default: null },
  saving: { type: Boolean, default: false },
  error: { type: String, default: '' },
  importedAsos: { type: Array, default: () => [] },
})
const emit = defineEmits(['close','submit'])
const form = reactive({name:'',cpf:'',registration:'',job_title:'',department:'',admitted_at:'',terminated_at:'',situation:'ATIVO'})
const currentAsos = computed(() => props.row?.aso_preview || [])
const fieldClass = 'mt-1 w-full rounded-xl border border-app-border bg-app-bg px-3 py-2.5 text-sm text-app-text outline-none focus:border-app-primary focus:ring-2 focus:ring-app-primary/15'
watch(() => props.row, row => {
  if (!row) return
  const data = row.data || {}
  for (const field of ['name','cpf','registration','job_title','department','admitted_at','terminated_at']) form[field] = data[field] ?? ''
  form.situation = data.is_away ? 'AFASTADO' : data.terminated_at ? 'DESLIGADO' : 'ATIVO'
}, { immediate:true })
function close(){if(!props.saving) emit('close')}
function submit(){emit('submit',{...form})}
function onKey(e){if(e.key==='Escape' && props.open) close()}
onMounted(()=>document.addEventListener('keydown',onKey))
onBeforeUnmount(()=>document.removeEventListener('keydown',onKey))
</script>

<template>
  <Teleport to="body">
    <div v-if="open && row" class="sae-import-modal-root" role="presentation">
      <div class="sae-import-modal-backdrop" @click="close"></div>
      <section role="dialog" aria-modal="true" :aria-label="`Corrigir linha ${row.row}`" class="sae-import-modal-panel">
        <header class="sae-import-modal-header">
          <div>
            <p class="text-xs font-black uppercase tracking-wider text-app-primary">Conferência da importação · Linha {{ row.row }}</p>
            <h2 class="mt-1 text-xl font-black text-app-text">Corrigir cadastro e vínculo</h2>
            <p class="mt-1 text-sm text-app-muted">Confira também os ASOs reconhecidos nesta mesma linha.</p>
          </div>
          <button type="button" aria-label="Fechar modal" class="rounded-xl border border-app-border px-3 py-2 text-app-muted hover:text-app-text" :disabled="saving" @click="close"><PhX class="h-5 w-5"/></button>
        </header>
        <form class="sae-import-modal-body" @submit.prevent="submit">
          <div v-if="error" role="alert" class="mb-4 rounded-xl border border-app-danger/35 bg-app-danger/10 p-3 text-sm text-app-danger">{{ error }}</div>
          <div v-if="row.issues?.length" class="mb-5 rounded-xl border border-app-warning/40 bg-app-warning/10 p-4">
            <div class="mb-2 flex items-center gap-2 text-sm font-bold text-app-text"><PhWarningCircle class="h-5 w-5 text-app-warning"/> Pontos identificados pelo SAE</div>
            <p v-for="(issue,i) in row.issues" :key="i" class="text-sm text-app-muted">• {{issue}}</p>
          </div>
          <section class="mb-6">
            <h3 class="mb-3 flex items-center gap-2 border-b border-app-border pb-2 font-bold text-app-text"><PhUser class="h-5 w-5 text-app-primary"/> Dados do colaborador</h3>
            <div class="grid gap-4 md:grid-cols-2">
              <label class="text-sm font-medium text-app-text">Nome completo *<input v-model="form.name" :class="fieldClass" maxlength="255" required/></label>
              <label class="text-sm font-medium text-app-text">CPF *<input v-model="form.cpf" :class="fieldClass" inputmode="numeric" maxlength="20" required/></label>
            </div>
          </section>
          <section class="mb-6">
            <h3 class="mb-3 flex items-center gap-2 border-b border-app-border pb-2 font-bold text-app-text"><PhIdentificationCard class="h-5 w-5 text-app-primary"/> Dados do vínculo</h3>
            <div class="grid gap-4 md:grid-cols-2">
              <label class="text-sm font-medium text-app-text">Matrícula<input v-model="form.registration" :class="fieldClass" maxlength="255"/></label>
              <label class="text-sm font-medium text-app-text">Função<input v-model="form.job_title" :class="fieldClass" maxlength="255"/></label>
              <label class="text-sm font-medium text-app-text">Setor<input v-model="form.department" :class="fieldClass" maxlength="255"/></label>
              <label class="text-sm font-medium text-app-text">Situação<select v-model="form.situation" :class="fieldClass"><option value="ATIVO">Ativo</option><option value="AFASTADO">Afastado</option><option value="DESLIGADO">Desligado</option></select></label>
              <label class="text-sm font-medium text-app-text">Admissão *<input v-model="form.admitted_at" type="date" :class="fieldClass" required/></label>
              <label class="text-sm font-medium text-app-text">Demissão<input v-model="form.terminated_at" type="date" :min="form.admitted_at || undefined" :class="fieldClass"/></label>
            </div>
          </section>
          <section>
            <div class="mb-3 flex items-center justify-between border-b border-app-border pb-2"><h3 class="flex items-center gap-2 font-bold text-app-text"><PhClipboardText class="h-5 w-5 text-app-primary"/> ASOs identificados</h3><span class="text-xs text-app-muted">{{ currentAsos.length }} coluna(s) com conteúdo</span></div>
            <div v-if="!currentAsos.length" class="rounded-xl border border-dashed border-app-border p-4 text-sm text-app-muted">Nenhum registro de ASO encontrado nesta linha.</div>
            <div v-else class="overflow-x-auto rounded-xl border border-app-border">
              <table class="w-full min-w-[480px] text-sm"><thead class="bg-app-surface text-left text-xs uppercase text-app-muted"><tr><th class="p-3">Coluna do Excel</th><th class="p-3">Conteúdo</th><th class="p-3">Situação</th></tr></thead>
              <tbody><tr v-for="(aso,index) in currentAsos" :key="index" class="border-t border-app-border text-app-text"><td class="p-3 font-medium">{{aso.column}}</td><td class="p-3">{{aso.value}}</td><td class="p-3 text-xs text-app-muted">Prévia — não alterado</td></tr></tbody></table>
            </div>
            <p class="mt-3 text-xs text-app-muted">Os ASOs são exibidos para conferência. Este modal salva apenas os dados cadastrais do lote; datas e tipos de exames ainda não são editáveis aqui.</p>
          </section>
        </form>
        <footer class="sae-import-modal-footer">
          <p class="hidden text-xs text-app-muted sm:block">A correção será revalidada antes da importação.</p>
          <div class="ml-auto flex gap-2"><BaseButton variant="secondary" :disabled="saving" @click="close">Cancelar</BaseButton><BaseButton :disabled="saving" @click="submit">{{ saving ? 'Revalidando...' : 'Salvar e revalidar' }}</BaseButton></div>
        </footer>
      </section>
    </div>
  </Teleport>
</template>
<style scoped>
.sae-import-modal-root { position: fixed; inset: 0; z-index: 9999; display: grid; place-items: center; padding: 20px; isolation: isolate; }
.sae-import-modal-backdrop { position: absolute; inset: 0; background-color: rgba(4, 12, 23, .82); backdrop-filter: blur(3px); }
.sae-import-modal-panel { position: relative; display: flex; flex-direction: column; width: min(100%, 900px); max-height: min(90dvh, 900px); min-width: 0; overflow: hidden; border: 1px solid rgb(var(--color-border)); border-radius: 20px; background-color: rgb(var(--color-bg)); color: rgb(var(--color-text)); box-shadow: 0 30px 100px rgba(0,0,0,.4); }
.sae-import-modal-header { padding: 20px 26px; display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-shrink: 0; border-bottom: 1px solid rgb(var(--color-border)); background-color: rgb(var(--color-surface)); }
.sae-import-modal-body { flex: 1; min-height: 0; padding: 24px 26px; overflow-y: auto; overscroll-behavior: contain; }
.sae-import-modal-footer { flex-shrink: 0; display: flex; justify-content: space-between; align-items: center; gap: 12px; border-top: 1px solid rgb(var(--color-border)); background-color: rgb(var(--color-surface)); padding: 16px 26px; }
@media(max-width:640px){.sae-import-modal-root{padding:8px}.sae-import-modal-panel{max-height:96dvh;border-radius:14px}.sae-import-modal-header,.sae-import-modal-body,.sae-import-modal-footer{padding:14px}}
</style>
