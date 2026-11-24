<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { PhArrowLeft, PhMagnifyingGlass, PhPlus, PhX, PhClipboardText } from '@phosphor-icons/vue'
import PageHeader from '@/shared/components/layout/PageHeader.vue'
import ContentCard from '@/shared/components/cards/ContentCard.vue'
import BaseButton from '@/shared/components/buttons/BaseButton.vue'
import LoadingSpinner from '@/shared/components/feedback/LoadingSpinner.vue'
import SaeViewToggle from '../components/SaeViewToggle.vue'
import { useTeamMemberStore } from '../stores/teamMember.store'
import { asoService } from '../services/asoOperations.service'

const route = useRoute()
const router = useRouter()
const store = useTeamMemberStore()
const companyUuid = computed(() => String(route.params.companyUuid))
const people = computed(() => store.items ?? [])
const pagination = computed(() => store.pagination ?? { currentPage: 1, lastPage: 1, total: 0 })
const search = ref(String(route.query.search || ''))
const viewMode = ref('table')
const selectedPersonId = ref(String(route.query.employee || ''))
const selectedLinkId = ref(String(route.query.link || ''))
const drawerOpen = ref(false)
const drawerBusy = ref(false)
const saving = ref(false)
const error = ref('')
const notice = ref('')
const asoRecords = ref([])
const asoForm = reactive({ exam_type:'periodico', performed_at:'', next_due_at:'', esocial_submitted:true, receipt_number:'', notes:'' })

const key = item => String(item?.uuid ?? item?.id ?? '')
const dateLabel = d => d ? String(d).slice(0,10).split('-').reverse().join('/') : '—'
const examName = type => ({admissional:'Admissional',periodico:'Periódico',retorno:'Retorno ao trabalho',mudanca_risco:'Mudança de riscos ocupacionais',demissional:'Demissional'})[type] ?? type
const orderedLinks = person => [...(person?.links || [])].sort((a,b) => Number(Boolean(a.terminated_at)) - Number(Boolean(b.terminated_at)) || String(b.admitted_at || '').localeCompare(String(a.admitted_at || '')))
const currentLink = person => orderedLinks(person).find(l => !l.terminated_at) || null
const situation = person => { const link=currentLink(person); return !link ? 'Desligado' : link.is_away ? 'Afastado' : 'Ativo' }
const selectedPerson = computed(() => people.value.find(p => key(p) === selectedPersonId.value) ?? null)
const selectedLink = computed(() => orderedLinks(selectedPerson.value).find(l => key(l) === selectedLinkId.value) ?? null)
const selectedLinks = computed(() => orderedLinks(selectedPerson.value))
const apiError = e => e?.response?.data?.errors ? Object.values(e.response.data.errors).flat().join(' ') : e?.response?.data?.message || e?.message || 'Não foi possível concluir a operação.'

async function load(page=1) {
  error.value = ''
  store.setFilters({managed_company_uuid:companyUuid.value, search:search.value.trim(), multiple_links:0, history_only:0, page})
  try {
    await store.fetchItems()
    if (store.error) throw new Error(store.error)
  } catch(e) { error.value=apiError(e) }
}
async function openPerson(person, preferredLink=null) {
  selectedPersonId.value=key(person)
  const link=preferredLink || currentLink(person) || orderedLinks(person)[0] || null
  selectedLinkId.value=key(link)
  drawerOpen.value=true
  await loadAsos()
}
async function selectLink(link) { selectedLinkId.value=key(link); await loadAsos() }
async function loadAsos() {
  asoRecords.value=[]
  if (!selectedLink.value) return
  drawerBusy.value=true; error.value=''
  try {
    const result=await asoService(companyUuid.value, selectedLinkId.value).list()
    const payload=result?.data?.data ? result.data : result
    asoRecords.value=Array.isArray(payload?.data) ? payload.data : Array.isArray(payload) ? payload : []
  } catch(e) { error.value=apiError(e) }
  finally { drawerBusy.value=false }
}
function closeDrawer() { if (!saving.value) drawerOpen.value=false }
async function saveAso() {
  if (!selectedLink.value) return
  saving.value=true; error.value=''; notice.value=''
  try {
    await asoService(companyUuid.value, selectedLinkId.value).create({
      ...asoForm,
      next_due_at:asoForm.exam_type === 'demissional' ? null : asoForm.next_due_at,
      receipt_number:asoForm.esocial_submitted ? (asoForm.receipt_number || null) : null,
      notes:asoForm.notes || null,
    })
    Object.assign(asoForm,{exam_type:'periodico',performed_at:'',next_due_at:'',esocial_submitted:true,receipt_number:'',notes:''})
    notice.value='ASO registrado com sucesso.'
    await loadAsos()
  } catch(e) { error.value=apiError(e) }
  finally { saving.value=false }
}
function toTeam() { router.push({name:'managed-company-team',params:{companyUuid:companyUuid.value}}) }
async function initialLoad() {
  await load()
  const person=people.value.find(p => key(p) === String(route.query.employee || ''))
  if (person) {
    const link=orderedLinks(person).find(l => key(l) === String(route.query.link || ''))
    await openPerson(person,link)
  }
}
onMounted(initialLoad)
watch(companyUuid,() => {drawerOpen.value=false;selectedPersonId.value='';selectedLinkId.value='';search.value='';load()})
</script>

<template>
  <section>
    <PageHeader eyebrow="ASOs · Operação" title="Gestão de ASOs da empresa" description="Lançamentos e histórico por colaborador e por vínculo. O cadastro administrativo permanece em Empresas.">
      <template #actions>
        <BaseButton variant="secondary" @click="router.push({name:'aso-monitoring'})"><PhArrowLeft class="h-4 w-4" /> Todas as empresas</BaseButton>
        <BaseButton variant="secondary" @click="toTeam">Editar equipe</BaseButton>
      </template>
    </PageHeader>

    <ContentCard>
      <div class="sae-aso-tip"><PhClipboardText class="h-5 w-5 shrink-0 text-app-primary"/><p><strong>Central operacional de exames</strong><span> Selecione um colaborador para ver os vínculos, consultar os ASOs e registrar novos lançamentos.</span></p></div>
      <form class="mb-5 flex flex-wrap items-end gap-3 border-b border-app-border pb-5" @submit.prevent="load(1)">
        <label class="min-w-56 flex-1 text-sm font-bold text-app-text">Buscar colaborador
          <input v-model="search" class="sae-aso-input mt-2" placeholder="Nome, CPF ou matrícula" />
        </label>
        <BaseButton variant="secondary" type="submit"><PhMagnifyingGlass class="h-4 w-4"/> Buscar</BaseButton>
        <SaeViewToggle v-model="viewMode" />
      </form>
      <p v-if="error && !drawerOpen" class="mb-4 text-sm text-app-danger" role="alert">{{ error }}</p>
      <div v-if="store.isLoading" class="flex min-h-44 items-center justify-center"><LoadingSpinner/></div>
      <div v-else-if="!people.length" class="py-14 text-center text-sm text-app-muted">Nenhum colaborador encontrado nesta empresa.</div>
      <div v-else-if="viewMode==='cards'" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <article v-for="person in people" :key="key(person)" class="sae-aso-card">
          <div class="flex items-start justify-between gap-2"><h3 class="font-bold text-app-text">{{ person.name }}</h3><span class="sae-aso-status" :class="situation(person)==='Ativo'?'ok':situation(person)==='Afastado'?'warn':'off'">{{ situation(person) }}</span></div>
          <p class="mt-1 text-xs text-app-muted">CPF: {{ person.cpf }}</p>
          <dl class="my-4 space-y-2 text-sm text-app-text"><div>Função: <strong>{{ currentLink(person)?.job_title || 'Não informada' }}</strong></div><div>Matrícula: {{ currentLink(person)?.registration || 'Não informada' }}</div><div>{{ person.links?.length ?? 0 }} vínculo(s)</div></dl>
          <button class="sae-aso-button primary w-full" @click="openPerson(person)">Gerenciar ASOs</button>
        </article>
      </div>
      <div v-else class="overflow-x-auto rounded-2xl border border-app-border">
        <table class="sae-aso-table min-w-full text-sm">
          <thead><tr><th>Colaborador</th><th>CPF</th><th>Função atual</th><th>Vínculos</th><th>Situação</th><th>Ações</th></tr></thead>
          <tbody><tr v-for="person in people" :key="key(person)"><td class="font-semibold">{{ person.name }}</td><td>{{ person.cpf }}</td><td>{{ currentLink(person)?.job_title || '—' }}</td><td>{{ person.links?.length ?? 0 }}</td><td><span class="sae-aso-status" :class="situation(person)==='Ativo'?'ok':situation(person)==='Afastado'?'warn':'off'">{{ situation(person) }}</span></td><td><button class="sae-aso-button" @click="openPerson(person)">Abrir ASOs</button></td></tr></tbody>
        </table>
      </div>
      <div v-if="pagination.lastPage > 1" class="mt-5 flex items-center justify-between gap-3 text-sm text-app-muted">
        <span>{{ pagination.total }} colaboradores</span><div class="flex items-center gap-3"><button class="sae-aso-button" :disabled="pagination.currentPage<=1" @click="load(pagination.currentPage-1)">Anterior</button><span>{{ pagination.currentPage }} / {{ pagination.lastPage }}</span><button class="sae-aso-button" :disabled="pagination.currentPage>=pagination.lastPage" @click="load(pagination.currentPage+1)">Próxima</button></div>
      </div>
    </ContentCard>

    <div v-if="drawerOpen" class="fixed inset-0 z-50 bg-black/60" @click.self="closeDrawer">
      <div class="sae-aso-drawer absolute inset-y-0 right-0 flex w-full max-w-2xl flex-col border-l border-app-border bg-app-bg shadow-2xl" role="dialog" aria-modal="true" aria-label="Gerenciar exames do colaborador">
        <div class="flex items-start justify-between gap-4 border-b border-app-border bg-app-surface px-5 py-5 sm:px-7">
          <div class="min-w-0"><p class="text-xs font-bold uppercase tracking-widest text-app-primary">Gestão de ASOs</p><h2 class="mt-1 text-xl font-bold text-app-text">{{ selectedPerson?.name }}</h2><p class="mt-1 text-xs text-app-muted">CPF {{ selectedPerson?.cpf }} · {{ selectedLinks.length }} vínculo(s)</p></div>
          <button class="sae-aso-button" aria-label="Fechar painel" @click="closeDrawer"><PhX class="h-4 w-4"/></button>
        </div>
        <div class="flex-1 space-y-5 overflow-y-auto px-5 py-5 sm:px-7">
          <p v-if="error" class="rounded-lg bg-app-danger/10 p-3 text-sm text-app-danger" role="alert">{{ error }}</p>
          <p v-if="notice" class="rounded-lg bg-app-success/10 p-3 text-sm text-app-success" role="status">{{ notice }}</p>
          <section><h3 class="mb-2 text-sm font-bold text-app-text">Selecione o vínculo</h3><div class="flex gap-2 overflow-x-auto pb-1"><button v-for="(link,index) in selectedLinks" :key="key(link)" class="sae-aso-link shrink-0" :class="key(link)===selectedLinkId?'selected':''" @click="selectLink(link)"><strong>Vínculo {{ index+1 }}</strong><span>{{ link.registration || 'Sem matrícula' }}</span><small>{{ link.terminated_at ? 'Desligado' : link.is_away ? 'Afastado' : 'Ativo' }}</small></button></div></section>
          <template v-if="selectedLink">
            <section class="rounded-xl border border-app-border bg-app-surface p-4"><h3 class="font-bold text-app-text">{{ selectedLink.job_title || 'Função não informada' }}</h3><p class="mt-1 text-xs text-app-muted">Matrícula: {{ selectedLink.registration || 'Não informada' }}</p><p class="mt-1 text-xs text-app-muted">Admissão {{ dateLabel(selectedLink.admitted_at) }} · {{ selectedLink.terminated_at ? 'Desligamento '+dateLabel(selectedLink.terminated_at) : 'Vínculo vigente' }}</p></section>
            <section class="space-y-3"><div class="flex items-center justify-between"><h3 class="text-sm font-bold text-app-text">Histórico de ASOs</h3><span class="text-xs text-app-muted">{{ asoRecords.length }} exibido(s)</span></div><p v-if="drawerBusy" class="text-sm text-app-muted">Carregando exames...</p><p v-else-if="!asoRecords.length" class="rounded-lg border border-dashed border-app-border p-4 text-sm text-app-muted">Nenhum ASO registrado para este vínculo.</p><div v-else class="overflow-x-auto rounded-xl border border-app-border"><table class="sae-aso-table w-full text-xs"><thead><tr><th>Tipo</th><th>Realizado</th><th>Próximo</th><th>eSocial</th></tr></thead><tbody><tr v-for="aso in asoRecords" :key="key(aso)"><td class="font-semibold">{{ examName(aso.exam_type) }}</td><td>{{ dateLabel(aso.performed_at) }}</td><td>{{ dateLabel(aso.next_due_at) }}</td><td>{{ aso.esocial_submitted?'Lançado':'Não transmitido' }}</td></tr></tbody></table></div></section>
            <section class="rounded-xl border border-app-border bg-app-surface p-4"><h3 class="mb-1 flex items-center gap-2 text-sm font-bold text-app-text"><PhPlus class="h-4 w-4 text-app-primary"/> Novo lançamento de ASO</h3><p class="mb-4 text-xs text-app-muted">O ASO será associado exclusivamente ao vínculo selecionado.</p>
              <form class="grid grid-cols-1 gap-3 sm:grid-cols-2" @submit.prevent="saveAso">
                <label class="sae-aso-label">Tipo<select v-model="asoForm.exam_type" class="sae-aso-input"><option value="admissional">Admissional</option><option value="periodico">Periódico</option><option value="retorno">Retorno ao trabalho</option><option value="mudanca_risco">Mudança de riscos ocupacionais</option><option value="demissional">Demissional</option></select></label>
                <label class="sae-aso-label">Realizado em *<input v-model="asoForm.performed_at" type="date" class="sae-aso-input" required/></label>
                <label v-if="asoForm.exam_type!=='demissional'" class="sae-aso-label">Próximo vencimento *<input v-model="asoForm.next_due_at" type="date" class="sae-aso-input" :min="asoForm.performed_at" required/></label>
                <label class="sae-aso-label">Situação no eSocial<select v-model="asoForm.esocial_submitted" class="sae-aso-input"><option :value="true">Lançado no portal</option><option :value="false">Não transmitido</option></select></label>
                <label v-if="asoForm.esocial_submitted" class="sae-aso-label">Recibo (opcional)<input v-model="asoForm.receipt_number" class="sae-aso-input" maxlength="255"/></label>
                <label class="sae-aso-label sm:col-span-2">Observações<textarea v-model="asoForm.notes" class="sae-aso-input min-h-20" maxlength="3000"/></label>
                <div class="sm:col-span-2 flex justify-end"><button class="sae-aso-button primary" type="submit" :disabled="saving">{{ saving?'Salvando...':'Registrar ASO' }}</button></div>
              </form>
            </section>
          </template>
          <p v-else class="text-sm text-app-muted">Este colaborador não possui vínculos cadastrados nesta empresa.</p>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.sae-aso-tip { display:flex;gap:.7rem;align-items:center;margin-bottom:1.25rem;padding:.85rem 1rem;background:rgb(var(--color-primary-soft) / .38);border:1px solid rgb(var(--color-primary) / .17);border-radius:.85rem;color:rgb(var(--color-text));font-size:.82rem; }
.sae-aso-tip span { color:rgb(var(--color-muted)); }
.sae-aso-input {display:block;width:100%;min-width:0;border:1px solid rgb(var(--color-border));border-radius:.65rem;padding:.6rem .75rem;background:rgb(var(--color-bg));color:rgb(var(--color-text));outline:none;font-size:.85rem;}
.sae-aso-input:focus {border-color:rgb(var(--color-primary));box-shadow:0 0 0 3px rgb(var(--color-primary) / .10);}
.sae-aso-label {display:flex;flex-direction:column;gap:.35rem;color:rgb(var(--color-text));font-size:.79rem;font-weight:700;}
.sae-aso-table {border-collapse:collapse;background:rgb(var(--color-surface-strong));color:rgb(var(--color-text));}
.sae-aso-table th {padding:.8rem 1rem;text-align:left;white-space:nowrap;background:rgb(var(--color-primary-soft) / .48);font-weight:700;font-size:.72rem;}
.sae-aso-table td {padding:.8rem 1rem;border-top:1px solid rgb(var(--color-border));}
.sae-aso-table tbody tr:hover {background:rgb(var(--color-primary-soft) / .25);}
.sae-aso-button {display:inline-flex;align-items:center;justify-content:center;gap:.4rem;padding:.53rem .8rem;white-space:nowrap;border:1px solid rgb(var(--color-border));border-radius:.7rem;background:rgb(var(--color-surface));color:rgb(var(--color-text));font-size:.78rem;font-weight:700;}
.sae-aso-button:hover:not(:disabled) {border-color:rgb(var(--color-primary));color:rgb(var(--color-primary));}
.sae-aso-button.primary {background:rgb(var(--color-primary));color:rgb(var(--color-bg));border-color:rgb(var(--color-primary));}
.sae-aso-button:disabled {opacity:.5;cursor:not-allowed;}
.sae-aso-status {display:inline-flex;border-radius:100px;padding:.25rem .55rem;font-size:.7rem;font-weight:700;border:1px solid rgb(var(--color-border));}
.sae-aso-status.ok {color:rgb(var(--color-success));background:rgb(var(--color-success) / .10);border-color:rgb(var(--color-success) / .22);}
.sae-aso-status.warn {color:rgb(var(--color-warning));background:rgb(var(--color-warning) / .10);border-color:rgb(var(--color-warning) / .22);}
.sae-aso-status.off {color:rgb(var(--color-muted));background:rgb(var(--color-muted) / .10);}
.sae-aso-card {padding:1.15rem;border:1px solid rgb(var(--color-border));border-top:3px solid rgb(var(--color-primary));border-radius:1rem;background:rgb(var(--color-surface-strong));box-shadow:0 4px 15px rgb(0 0 0 / .04);}
.sae-aso-link {display:flex;min-width:145px;flex-direction:column;gap:.3rem;padding:.7rem;border:1px solid rgb(var(--color-border));border-radius:.75rem;text-align:left;background:rgb(var(--color-surface));color:rgb(var(--color-text));}
.sae-aso-link span,.sae-aso-link small {font-size:.72rem;color:rgb(var(--color-muted));}
.sae-aso-link.selected {border-color:rgb(var(--color-primary));background:rgb(var(--color-primary-soft) / .5);}
.sae-aso-drawer {animation:slidein .18s ease-out;}@keyframes slidein {from{transform:translateX(10px);opacity:.7;}to{transform:translateX(0);opacity:1;}}
</style>
