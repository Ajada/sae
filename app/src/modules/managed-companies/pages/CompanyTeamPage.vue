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
import SaeViewToggle from '../components/SaeViewToggle.vue'
import TeamLinkHistory from '../components/TeamLinkHistory.vue'

const route = useRoute()
const router = useRouter()
const store = useTeamMemberStore()
const companyUuid = computed(() => String(route.params.companyUuid))
const employees = computed(() => store.items ?? [])
const pagination = computed(() => store.pagination ?? { currentPage: 1, lastPage: 1, total: 0 })
const search = ref('')
const onlyMultiple = ref(false)
const historyOnly = ref(false)
const viewMode = ref('table')
const selectedLinkByEmployee = reactive({})
const error = ref('')
const notice = ref('')
const submitting = ref(false)
const formOpen = ref(false)
const linkFor = ref(null)
const expanded = ref(null)
const form = reactive({ name: '', cpf: '', registration: '', job_title: '', department: '', admitted_at: '', terminated_at: '' })
function openAsoManagement(person, link) {
  router.push({ name: 'aso-company', params: { companyUuid: companyUuid.value }, query: { employee: uuid(person), link: uuid(link), search: person.name } })
}
const awayDate = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Sao_Paulo', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date())
const onlyDigits = v => String(v ?? '').replace(/\D/g, '')
const dateLabel = value => value ? String(value).slice(0, 10).split('-').reverse().join('/') : '—'
const current = e => e.links?.find(l => !l.terminated_at)
const status = e => { const l = current(e); return !l ? 'Desligado' : l.is_away ? 'Afastado' : 'Ativo' }
const uuid = x => x.uuid || x.id
const orderedLinks = person => [...(person.links ?? [])].sort((a,b) => Number(Boolean(a.terminated_at)) - Number(Boolean(b.terminated_at)) || String(b.admitted_at ?? '').localeCompare(String(a.admitted_at ?? '')))
const selectedLink = person => orderedLinks(person).find(link => String(uuid(link)) === String(selectedLinkByEmployee[uuid(person)])) || orderedLinks(person)[0] || null
function selectLink(person, link) {
  if (link) selectedLinkByEmployee[uuid(person)] = String(uuid(link))
}
function toggleHistory(person) {
  if (expanded.value === uuid(person)) { expanded.value = null; return }
  expanded.value = uuid(person)
  selectLink(person, selectedLink(person))
}
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
    <PageHeader eyebrow="Gestão de empresas" title="Equipe da empresa" description="Cadastros de pessoas, matrículas e vínculos. Os exames são gerenciados exclusivamente no módulo ASOs.">
      <template #actions>
        <BaseButton variant="secondary" @click="router.push({ name: 'managed-companies' })"><PhArrowLeft class="h-4 w-4" /> Empresas</BaseButton>
        <BaseButton variant="secondary" @click="router.push({ name: 'managed-company-team-import', params: { companyUuid } })"><PhFileXls class="h-4 w-4" /> Importar Excel</BaseButton>
        <BaseButton @click="openNew"><PhPlus class="h-4 w-4" /> Cadastrar colaborador</BaseButton>
      </template>
    </PageHeader>

    <ContentCard>
      <div class="sae-context-bar"><span class="sae-context-marker"></span><div><strong>Cadastro e vínculos</strong><p>Atualize dados administrativos aqui. Para registrar exames, acesse ASOs.</p></div></div>
      <form class="flex flex-wrap items-end gap-3 border-b border-app-border pb-5" @submit.prevent="load()">
        <label class="min-w-56 flex-1 text-sm font-bold text-app-text">Buscar
          <input v-model="search" class="sae-input mt-2" placeholder="Nome, CPF ou matrícula" />
        </label>
        <label class="flex items-center gap-2 text-sm text-app-muted"><input v-model="onlyMultiple" type="checkbox" @change="load()" /> Mais de um vínculo</label>
        <label class="flex items-center gap-2 text-sm text-app-muted"><input v-model="historyOnly" type="checkbox" @change="load()" /> Sem vínculo vigente</label>
        <BaseButton type="submit" variant="secondary"><PhMagnifyingGlass class="h-4 w-4" /> Buscar</BaseButton>
        <SaeViewToggle v-model="viewMode" />
      </form>
      <BaseAlert v-if="error || store.error" class="mt-4" type="danger" :message="error || store.error" />
      <p v-if="notice" class="mt-4 text-sm text-app-text">{{ notice }}</p>
      <div v-if="store.isLoading" class="flex min-h-48 items-center justify-center"><LoadingSpinner /></div>
      <div v-else-if="!employees.length" class="flex min-h-48 flex-col items-center justify-center gap-3 text-center text-app-muted">
        <PhUsersThree class="h-9 w-9" /> Nenhum colaborador encontrado.
        <BaseButton @click="openNew">Cadastrar equipe</BaseButton>
      </div>
      <div v-else-if="viewMode === 'cards'" class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <article v-for="person in employees" :key="uuid(person)" class="sae-person-card rounded-2xl border border-app-border bg-app-surface p-4">
          <div class="flex items-start justify-between gap-2"><div><h3 class="font-bold text-app-text">{{ person.name }}</h3><p class="mt-1 text-xs text-app-muted">CPF: {{ person.cpf }}</p></div><span class="rounded-lg px-2 py-1 text-xs font-bold" :class="status(person) === 'Ativo' ? 'bg-app-success/10 text-app-success' : status(person) === 'Afastado' ? 'bg-app-warning/10 text-app-warning' : 'bg-app-muted/10 text-app-muted'">{{ status(person) }}</span></div>
          <div class="mt-4 space-y-2 text-sm text-app-text"><p><span class="text-app-muted">Função:</span> {{ current(person)?.job_title || 'Não informada' }}</p><p><span class="text-app-muted">Matrícula:</span> {{ current(person)?.registration || 'Não informada' }}</p><p><span class="text-app-muted">Vínculos:</span> {{ person.links?.length ?? 0 }}</p></div>
          <div class="mt-4 flex flex-wrap gap-2 border-t border-app-border pt-3"><button class="sae-action" @click="toggleHistory(person)">{{ expanded === uuid(person) ? 'Fechar histórico' : 'Histórico' }}</button><button class="sae-action" @click="openHistorical(person)">+ Vínculo</button></div>
          <div v-if="expanded === uuid(person)" class="mt-4"><TeamLinkHistory :person="person" :selected-link="selectedLink(person)" @select="link => selectLink(person, link)" @open-asos="link => openAsoManagement(person, link)" @registration="editRegistration" @toggle-away="link => patchLink(link, {is_away: !link.is_away, away_since: link.is_away ? null : awayDate()})" @terminate="terminate" /></div>
        </article>
      </div>
      <div v-else class="sae-table-shell mt-4 overflow-x-auto">
        <table class="sae-team-table min-w-full divide-y divide-app-border text-sm">
          <thead class="bg-app-primary-soft/40"><tr class="text-left text-xs uppercase tracking-wide text-app-muted"><th class="p-3">Colaborador</th><th class="p-3">CPF</th><th class="p-3">Matrícula atual</th><th class="p-3">Função</th><th class="p-3">Situação</th><th class="p-3">Vínculos</th><th class="p-3">Ações</th></tr></thead>
          <tbody class="divide-y divide-app-border">
            <template v-for="person in employees" :key="uuid(person)">
              <tr class="sae-data-row text-app-text">
                <td class="p-3 font-bold">{{ person.name }}</td>
                <td class="p-3">{{ person.cpf }}</td>
                <td class="p-3">{{ current(person)?.registration || '—' }}</td>
                <td class="p-3">{{ current(person)?.job_title || '—' }}</td>
                <td class="p-3"><span class="sae-status-pill" :class="status(person) === 'Ativo' ? 'sae-pill-active' : status(person) === 'Afastado' ? 'sae-pill-away' : 'sae-pill-off'">{{ status(person) }}</span></td>
                <td class="p-3">{{ person.links?.length ?? 0 }}</td>
                <td class="p-3"><div class="flex flex-wrap gap-2">
                  <button class="sae-action" @click="toggleHistory(person)">{{ expanded === uuid(person) ? 'Fechar' : 'Histórico' }}</button>
                  <button class="sae-action" @click="openHistorical(person)">+ Vínculo</button>
                </div></td>
              </tr>
              <tr v-if="expanded === uuid(person)"><td colspan="7" class="bg-app-primary-soft/15 p-4">
                <TeamLinkHistory :person="person" :selected-link="selectedLink(person)" @select="link => selectLink(person, link)" @open-asos="link => openAsoManagement(person, link)" @registration="editRegistration" @toggle-away="link => patchLink(link, {is_away: !link.is_away, away_since: link.is_away ? null : awayDate()})" @terminate="terminate" />
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
          <label class="sae-label">Data de desligamento do vínculo (não é data de ASO)<input v-model="form.terminated_at" class="sae-input" type="date" :min="form.admitted_at" /></label>
          <div class="sm:col-span-2 flex justify-end gap-3 border-t border-app-border pt-4"><BaseButton type="button" variant="secondary" @click="closeForm">Cancelar</BaseButton><BaseButton type="submit" :disabled="submitting">{{ submitting ? 'Salvando...' : 'Salvar cadastro' }}</BaseButton></div>
        </form>
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

<style scoped>
.sae-context-bar { display:flex; gap:.75rem; align-items:center; margin-bottom:1.1rem; padding:.75rem 1rem; border:1px solid rgb(var(--color-border)); border-radius:.85rem; background:rgb(var(--color-primary-soft) / .33); color:rgb(var(--color-text)); }
.sae-context-bar strong { font-size:.85rem; } .sae-context-bar p { color:rgb(var(--color-muted)); font-size:.75rem; }
.sae-context-marker { width:4px; height:2.3rem; background:rgb(var(--color-primary)); border-radius:100px; }
.sae-table-shell { border:1px solid rgb(var(--color-border));border-radius:1rem; }
.sae-team-table thead th { background:rgb(var(--color-primary-soft) / .42); color:rgb(var(--color-text)); font-weight:700; letter-spacing:.035em; }
.sae-data-row { transition: background .15s ease; } .sae-data-row:hover { background:rgb(var(--color-primary-soft) / .27); }
.sae-person-card { background:rgb(var(--color-surface-strong));box-shadow:0 4px 14px rgb(0 0 0 / .035);border-top:3px solid rgb(var(--color-primary) / .65); transition:border-color .15s,transform .15s; }
.sae-person-card:hover { border-color:rgb(var(--color-primary) / .6); transform:translateY(-1px); }
.sae-status-pill { display:inline-flex;border-radius:100px;padding:.3rem .65rem;border:1px solid transparent;font-size:.72rem;font-weight:700;white-space:nowrap; }
.sae-pill-active { color:rgb(var(--color-success));background:rgb(var(--color-success) / .10);border-color:rgb(var(--color-success) / .24); }
.sae-pill-away { color:rgb(var(--color-warning));background:rgb(var(--color-warning) / .10);border-color:rgb(var(--color-warning) / .24); }
.sae-pill-off { color:rgb(var(--color-muted));background:rgb(var(--color-muted) / .10);border-color:rgb(var(--color-border)); }
</style>
