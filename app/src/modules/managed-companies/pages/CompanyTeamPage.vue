<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { PhArrowLeft, PhPlus, PhMagnifyingGlass, PhUsersThree, PhFileXls } from '@phosphor-icons/vue'
import PageHeader from '@/shared/components/layout/PageHeader.vue'
import ContentCard from '@/shared/components/cards/ContentCard.vue'
import BaseButton from '@/shared/components/buttons/BaseButton.vue'
import BaseAlert from '@/shared/components/feedback/BaseAlert.vue'
import LoadingSpinner from '@/shared/components/feedback/LoadingSpinner.vue'
import { useTeamMemberStore } from '../stores/teamMember.store'
import { employmentLinkService, employeeLinksService } from '../services/teamMember.service'
import { asoService } from '../services/asoOperations.service'

const route = useRoute()
const router = useRouter()
const store = useTeamMemberStore()
const companyUuid = computed(() => String(route.params.companyUuid))
const employees = computed(() => store.items ?? [])
const pagination = computed(() => store.pagination ?? { currentPage: 1, lastPage: 1, total: 0 })
const search = ref('')
const onlyMultiple = ref(false)
const historyOnly = ref(false)
const error = ref('')
const notice = ref('')
const submitting = ref(false)
const formOpen = ref(false)
const linkFor = ref(null)
const expanded = ref(null)
const form = reactive({ name: '', cpf: '', registration: '', job_title: '', department: '', admitted_at: '', terminated_at: '' })
const asoFor = ref(null)
const asoRecords = ref([])
const asoLoading = ref(false)
const asoSaving = ref(false)
const asoForm = reactive({ exam_type: 'periodico', performed_at: '', next_due_at: '', esocial_submitted: true, receipt_number: '' })
async function openAsos(link, employee) {
  asoFor.value = { link, employee }
  Object.assign(asoForm, {exam_type:'periodico', performed_at:'', next_due_at:'', esocial_submitted:true, receipt_number:''})
  asoLoading.value = true; error.value = ''
  try {
    const res = await asoService(companyUuid.value, uuid(link)).list()
    const body = res?.data?.data ? res.data : res
    asoRecords.value = body?.data ?? []
  } catch(e) { error.value = apiError(e) }
  finally { asoLoading.value = false }
}
async function saveAso() {
  asoSaving.value = true; error.value = ''; notice.value = ''
  try {
    await asoService(companyUuid.value, uuid(asoFor.value.link)).create({
      ...asoForm, next_due_at: asoForm.exam_type === 'demissional' ? null : (asoForm.next_due_at || null),
      receipt_number: asoForm.esocial_submitted ? (asoForm.receipt_number || null) : null,
    })
    const res = await asoService(companyUuid.value, uuid(asoFor.value.link)).list()
    const body = res?.data?.data ? res.data : res
    asoRecords.value = body?.data ?? []
    notice.value = 'ASO registrado com sucesso.'
  } catch(e) { error.value = apiError(e) }
  finally { asoSaving.value = false }
}
const awayDate = () => new Date().toISOString().slice(0, 10)
const onlyDigits = v => String(v ?? '').replace(/\D/g, '')
const dateLabel = value => value ? String(value).slice(0, 10).split('-').reverse().join('/') : '—'
const current = e => e.links?.find(l => !l.terminated_at)
const status = e => { const l = current(e); return !l ? 'Desligado' : l.is_away ? 'Afastado' : 'Ativo' }
const uuid = x => x.uuid || x.id
const apiError = e => {
  const data = e?.response?.data
  return data?.errors ? Object.values(data.errors).flat().join(' ') : data?.message || e?.message || 'Não foi possível concluir a operação.'
}
async function load(page = 1) {
  error.value = ''
  store.setFilters({ managed_company_uuid: companyUuid.value, search: search.value.trim(), multiple_links: onlyMultiple.value ? 1 : 0, history_only: historyOnly.value ? 1 : 0, page })
  try { await store.fetchItems(); if (store.error) error.value = store.error }
  catch (e) { error.value = apiError(e) }
}
onMounted(() => load())
watch(companyUuid, () => { expanded.value = null; load() })
function resetForm() {
  Object.assign(form, { name: '', cpf: '', registration: '', job_title: '', department: '', admitted_at: '', terminated_at: '' })
}
function openNew() { linkFor.value = null; resetForm(); formOpen.value = true; error.value = ''; notice.value = '' }
function openHistorical(employee) { linkFor.value = employee; resetForm(); formOpen.value = true; error.value = ''; notice.value = '' }
function closeForm() { if (!submitting.value) formOpen.value = false }
async function save() {
  error.value = ''; notice.value = ''; submitting.value = true
  const fields = { ...form, registration: form.registration.trim() || null, job_title: form.job_title.trim() || null, department: form.department.trim() || null, terminated_at: form.terminated_at || null }
  try {
    if (linkFor.value) {
      await employeeLinksService(uuid(linkFor.value)).create({ ...fields, managed_company_uuid: companyUuid.value })
    } else {
      store.error = null
      await store.createItem({ ...fields, cpf: onlyDigits(form.cpf), managed_company_uuid: companyUuid.value })
      if (store.error) throw new Error(store.error)
    }
    formOpen.value = false; linkFor.value = null
    notice.value = 'Cadastro salvo com sucesso.'
    await load()
  } catch (e) { error.value = apiError(e) }
  finally { submitting.value = false }
}
async function patchLink(link, payload, confirmText = null) {
  if (confirmText && !window.confirm(confirmText)) return
  error.value = ''; notice.value = ''; submitting.value = true
  try {
    await employmentLinkService.update(uuid(link), payload)
    notice.value = 'Vínculo atualizado.'
    await load(pagination.value.currentPage || 1)
  } catch (e) { error.value = apiError(e) }
  finally { submitting.value = false }
}
function terminate(link) {
  const date = window.prompt('Informe a data do desligamento (AAAA-MM-DD):', awayDate())
  if (date === null) return
  if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) { error.value = 'Informe uma data no formato AAAA-MM-DD.'; return }
  patchLink(link, { terminated_at: date, is_away: false }, 'Confirmar desligamento deste vínculo?')
}
function editRegistration(link) {
  const registration = window.prompt('Matrícula (deixe vazio para não informar):', link.registration || '')
  if (registration !== null) patchLink(link, { registration: registration.trim() || null })
}
</script>

<template>
  <section>
    <PageHeader eyebrow="Gestão de empresas" title="Equipe da empresa" description="Colaboradores únicos por CPF, com vínculos e matrículas preservados no histórico.">
      <template #actions>
        <BaseButton variant="secondary" @click="router.push({ name: 'managed-companies' })"><PhArrowLeft class="h-4 w-4" /> Empresas</BaseButton>
        <BaseButton variant="secondary" @click="router.push({ name: 'managed-company-team-import', params: { companyUuid } })"><PhFileXls class="h-4 w-4" /> Importar Excel</BaseButton>
        <BaseButton @click="openNew"><PhPlus class="h-4 w-4" /> Cadastrar colaborador</BaseButton>
      </template>
    </PageHeader>

    <ContentCard>
      <form class="flex flex-wrap items-end gap-3 border-b border-app-border pb-5" @submit.prevent="load()">
        <label class="min-w-56 flex-1 text-sm font-bold text-app-text">Buscar
          <input v-model="search" class="sae-input mt-2" placeholder="Nome, CPF ou matrícula" />
        </label>
        <label class="flex items-center gap-2 text-sm text-app-muted"><input v-model="onlyMultiple" type="checkbox" @change="load()" /> Mais de um vínculo</label>
        <label class="flex items-center gap-2 text-sm text-app-muted"><input v-model="historyOnly" type="checkbox" @change="load()" /> Sem vínculo vigente</label>
        <BaseButton type="submit" variant="secondary"><PhMagnifyingGlass class="h-4 w-4" /> Buscar</BaseButton>
      </form>
      <BaseAlert v-if="error || store.error" class="mt-4" type="danger" :message="error || store.error" />
      <p v-if="notice" class="mt-4 text-sm text-app-text">{{ notice }}</p>
      <div v-if="store.isLoading" class="flex min-h-48 items-center justify-center"><LoadingSpinner /></div>
      <div v-else-if="!employees.length" class="flex min-h-48 flex-col items-center justify-center gap-3 text-center text-app-muted">
        <PhUsersThree class="h-9 w-9" /> Nenhum colaborador encontrado.
        <BaseButton @click="openNew">Cadastrar equipe</BaseButton>
      </div>
      <div v-else class="mt-4 overflow-x-auto">
        <table class="min-w-full divide-y divide-app-border text-sm">
          <thead><tr class="text-left text-xs uppercase tracking-wide text-app-muted"><th class="p-3">Colaborador</th><th class="p-3">CPF</th><th class="p-3">Matrícula atual</th><th class="p-3">Função</th><th class="p-3">Situação</th><th class="p-3">Vínculos</th><th class="p-3">Ações</th></tr></thead>
          <tbody class="divide-y divide-app-border">
            <template v-for="person in employees" :key="uuid(person)">
              <tr class="text-app-text">
                <td class="p-3 font-bold">{{ person.name }}</td>
                <td class="p-3">{{ person.cpf }}</td>
                <td class="p-3">{{ current(person)?.registration || '—' }}</td>
                <td class="p-3">{{ current(person)?.job_title || '—' }}</td>
                <td class="p-3"><span class="rounded-lg border border-app-border px-2 py-1">{{ status(person) }}</span></td>
                <td class="p-3">{{ person.links?.length ?? 0 }}</td>
                <td class="p-3"><div class="flex flex-wrap gap-2">
                  <button class="sae-action" @click="expanded = expanded === uuid(person) ? null : uuid(person)">{{ expanded === uuid(person) ? 'Fechar' : 'Histórico' }}</button>
                  <button class="sae-action" @click="openHistorical(person)">+ Vínculo</button>
                </div></td>
              </tr>
              <tr v-if="expanded === uuid(person)"><td colspan="7" class="bg-app-surface p-4">
                <p class="mb-3 font-bold text-app-text">Vínculos de {{ person.name }}</p>
                <div v-for="link in person.links" :key="uuid(link)" class="mb-2 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-app-border p-3 text-sm text-app-text">
                  <div><b>{{ link.registration || 'Sem matrícula' }}</b> · {{ link.job_title || 'Sem função' }}<br />
                    <span class="text-app-muted">{{ dateLabel(link.admitted_at) }} — {{ link.terminated_at ? dateLabel(link.terminated_at) : 'Atual' }} · {{ link.terminated_at ? 'Desligado' : link.is_away ? 'Afastado' : 'Ativo' }}</span></div>
                  <div class="flex flex-wrap gap-2">
                    <button class="sae-action" @click="openAsos(link, person)">ASOs</button>
                    <button class="sae-action" @click="editRegistration(link)">Matrícula</button>
                    <template v-if="!link.terminated_at">
                      <button class="sae-action" @click="patchLink(link, { is_away: !link.is_away, away_since: link.is_away ? null : awayDate() })">{{ link.is_away ? 'Reabilitar' : 'Afastar' }}</button>
                      <button class="sae-action" @click="terminate(link)">Desligar</button>
                    </template>
                  </div>
                </div>
              </td></tr>
            </template>
          </tbody>
        </table>
      </div>
      <div v-if="pagination.lastPage > 1" class="mt-4 flex items-center justify-between text-sm text-app-muted">
        <span>{{ pagination.total }} colaboradores</span><div class="flex items-center gap-3">
          <BaseButton size="sm" variant="secondary" :disabled="pagination.currentPage <= 1" @click="load(pagination.currentPage - 1)">Anterior</BaseButton>
          {{ pagination.currentPage }} / {{ pagination.lastPage }}
          <BaseButton size="sm" variant="secondary" :disabled="pagination.currentPage >= pagination.lastPage" @click="load(pagination.currentPage + 1)">Próxima</BaseButton>
        </div>
      </div>
    </ContentCard>

    <div v-if="formOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" @click.self="closeForm">
      <div class="w-full max-w-2xl rounded-2xl border border-app-border bg-app-bg p-6 shadow-2xl">
        <div class="mb-5 flex items-start justify-between"><div><h2 class="text-xl font-black text-app-text">{{ linkFor ? `Novo vínculo: ${linkFor.name}` : 'Cadastrar colaborador e vínculo' }}</h2><p class="text-sm text-app-muted">A matrícula é opcional. É possível manter vínculos históricos.</p></div><button class="sae-action" @click="closeForm">Fechar</button></div>
        <form class="grid grid-cols-1 gap-4 sm:grid-cols-2" @submit.prevent="save">
          <template v-if="!linkFor"><label class="sae-label sm:col-span-2">Nome completo *<input v-model="form.name" class="sae-input" required maxlength="255" /></label><label class="sae-label sm:col-span-2">CPF *<input v-model="form.cpf" class="sae-input" required inputmode="numeric" maxlength="14" placeholder="Somente números ou com máscara" /></label></template>
          <label class="sae-label">Matrícula<input v-model="form.registration" class="sae-input" maxlength="80" /></label>
          <label class="sae-label">Função<input v-model="form.job_title" class="sae-input" maxlength="255" /></label>
          <label class="sae-label">Setor<input v-model="form.department" class="sae-input" maxlength="255" /></label>
          <label class="sae-label">Admissão *<input v-model="form.admitted_at" class="sae-input" type="date" required /></label>
          <label class="sae-label">Demissão (somente vínculo histórico)<input v-model="form.terminated_at" class="sae-input" type="date" :min="form.admitted_at" /></label>
          <div class="sm:col-span-2 flex justify-end gap-3 border-t border-app-border pt-4"><BaseButton type="button" variant="secondary" @click="closeForm">Cancelar</BaseButton><BaseButton type="submit" :disabled="submitting">{{ submitting ? 'Salvando...' : 'Salvar cadastro' }}</BaseButton></div>
        </form>
      </div>
    </div>

    <div v-if="asoFor" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" @click.self="asoFor=null">
      <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl border border-app-border bg-app-bg p-6 shadow-2xl">
        <div class="mb-4 flex items-center justify-between gap-3"><div><h2 class="text-xl font-bold text-app-text">ASOs · {{asoFor.employee.name}}</h2><p class="text-xs text-app-muted">Matrícula: {{asoFor.link.registration || 'não informada'}}</p></div><button class="sae-action" @click="asoFor=null">Fechar</button></div>
        <form class="grid grid-cols-1 gap-3 sm:grid-cols-2" @submit.prevent="saveAso">
          <label class="sae-label">Tipo de ASO
            <select v-model="asoForm.exam_type" class="sae-input"><option value="admissional">Admissional</option><option value="periodico">Periódico</option><option value="retorno">Retorno ao trabalho</option><option value="mudanca_risco">Mudança de riscos</option><option value="demissional">Demissional</option></select>
          </label>
          <label class="sae-label">Data de realização *<input v-model="asoForm.performed_at" type="date" class="sae-input" required /></label>
          <label v-if="asoForm.exam_type !== 'demissional'" class="sae-label">Próximo vencimento conforme PCMSO *<input v-model="asoForm.next_due_at" type="date" :min="asoForm.performed_at" class="sae-input" required /></label>
          <label class="sae-label">Registro eSocial
            <select v-model="asoForm.esocial_submitted" class="sae-input"><option :value="true">Lançado no portal</option><option :value="false">Ainda não transmitido</option></select>
          </label>
          <label v-if="asoForm.esocial_submitted" class="sae-label">Recibo do eSocial (opcional)<input v-model="asoForm.receipt_number" class="sae-input" maxlength="255" /></label>
          <div class="sm:col-span-2 flex justify-end"><BaseButton type="submit" :disabled="asoSaving">{{asoSaving ? 'Salvando...' : 'Registrar ASO'}}</BaseButton></div>
        </form>
        <div class="mt-5 border-t border-app-border pt-4"><p class="mb-2 font-bold text-app-text">Histórico do vínculo</p><p v-if="asoLoading" class="text-app-muted">Carregando...</p>
          <div v-for="aso in asoRecords" :key="aso.uuid" class="mb-2 rounded-lg border border-app-border p-3 text-sm text-app-text">
            <b>{{aso.exam_type}}</b> · {{dateLabel(aso.performed_at)}} · próximo: {{dateLabel(aso.next_due_at)}}
            <span class="ml-2 text-app-muted">{{aso.esocial_submitted ? 'Lançado' : 'Não lançado'}}</span>
          </div><p v-if="!asoRecords.length && !asoLoading" class="text-sm text-app-muted">Nenhum ASO cadastrado.</p>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.sae-input { display:block; width:100%; border:1px solid rgb(var(--color-border)); background:rgb(var(--color-bg)); color:rgb(var(--color-text)); border-radius:.75rem; padding:.7rem .85rem; font-size:.875rem; outline:none; }
.sae-input:focus { border-color:rgb(var(--color-primary)); }
.sae-label { display:flex; flex-direction:column; gap:.4rem; color:rgb(var(--color-text)); font-size:.875rem; font-weight:700; }
.sae-action { border:1px solid rgb(var(--color-border)); border-radius:.6rem; padding:.4rem .65rem; color:rgb(var(--color-text)); font-size:.78rem; background:rgb(var(--color-surface)); }
.sae-action:hover { border-color:rgb(var(--color-primary)); }
</style>
