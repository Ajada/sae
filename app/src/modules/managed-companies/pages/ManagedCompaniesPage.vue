<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { PhBuildings, PhMagnifyingGlass, PhPencilSimple, PhPlus, PhArchive, PhUsersThree } from '@phosphor-icons/vue'

import PageHeader from '@/shared/components/layout/PageHeader.vue'
import ContentCard from '@/shared/components/cards/ContentCard.vue'
import BaseButton from '@/shared/components/buttons/BaseButton.vue'
import BaseInput from '@/shared/components/forms/BaseInput.vue'
import BaseAlert from '@/shared/components/feedback/BaseAlert.vue'
import LoadingSpinner from '@/shared/components/feedback/LoadingSpinner.vue'
import ManagedCompanyDrawer from '../components/ManagedCompanyDrawer.vue'
import { useManagedCompanyStore } from '../stores/managedCompany.store'
import SaeViewToggle from '../components/SaeViewToggle.vue'

const store = useManagedCompanyStore()
const router = useRouter()
const isDrawerOpen = ref(false)
const editingCompany = ref(null)
const search = ref('')
const viewMode = ref('table')
const pageError = ref(null)
const notice = ref(null)
const companies = computed(() => store.items ?? [])
const pagination = computed(() => store.pagination ?? { currentPage: 1, lastPage: 1, total: 0, from: 0, to: 0 })
const hasCompanies = computed(() => companies.value.length > 0)
const keyOf = (company) => company.uuid || company.id

onMounted(async () => { await loadCompanies() })

async function loadCompanies() {
  try { await store.fetchItems() }
  catch (error) { pageError.value = error?.response?.data?.message || 'Não foi possível carregar as empresas.' }
}
async function handleSearch() {
  pageError.value = null
  store.setFilters({ search: search.value.trim(), page: 1 })
  await loadCompanies()
}
function openCreate() {
  pageError.value = null; notice.value = null; store.error = null
  editingCompany.value = null; isDrawerOpen.value = true
}
function openEdit(company) {
  pageError.value = null; notice.value = null; store.error = null
  editingCompany.value = company; isDrawerOpen.value = true
}
function closeDrawer() {
  if (store.isSaving) return
  isDrawerOpen.value = false; editingCompany.value = null
}
async function handleSubmit(payload) {
  pageError.value = null
  store.error = null
  try {
    if (editingCompany.value) await store.updateItem(keyOf(editingCompany.value), payload)
    else await store.createItem(payload)
    // A fábrica de CRUD pode reportar falhas pelo campo error sem lançar exception.
    if (store.error) return
    const updated = Boolean(editingCompany.value)
    isDrawerOpen.value = false
    editingCompany.value = null
    notice.value = updated ? 'Empresa atualizada com sucesso.' : 'Empresa cadastrada com sucesso.'
    await loadCompanies()
  } catch (error) {
    pageError.value = error?.response?.data?.message || 'Não foi possível salvar a empresa.'
  }
}
async function handleArchive(company) {
  if (!window.confirm(`Arquivar a empresa "${company.corporate_name}"? Seus dados não serão apagados definitivamente.`)) return
  pageError.value = null; notice.value = null; store.error = null
  try {
    await store.removeItem(keyOf(company))
    if (store.error) return
    notice.value = 'Empresa arquivada com sucesso.'
    if (companies.value.length === 0 && pagination.value.currentPage > 1) {
      store.setFilters({ page: pagination.value.currentPage - 1 })
    }
    await loadCompanies()
  } catch (error) { pageError.value = error?.response?.data?.message || 'Não foi possível arquivar a empresa.' }
}
async function goToPage(page) {
  if (page < 1 || page > pagination.value.lastPage) return
  store.setFilters({ page })
  await loadCompanies()
}
function cnpjDisplay(value) {
  const cnpj = String(value ?? '').replace(/\D/g, '')
  if (cnpj.length !== 14) return value || '—'
  return cnpj.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5')
}
function openTeam(company) {
  router.push({ name: 'managed-company-team', params: { companyUuid: keyOf(company) } })
}
</script>

<template>
  <section>
    <PageHeader eyebrow="Cadastros" title="Empresas" description="Cadastre e administre as empresas acompanhadas pelo SAE.">
      <template #actions>
        <BaseButton @click="openCreate"><PhPlus class="h-4 w-4" /> Nova empresa</BaseButton>
      </template>
    </PageHeader>

    <ContentCard>
      <div class="flex flex-col gap-4 border-b border-app-border pb-5 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <h2 class="text-lg font-black text-app-text">Empresas cadastradas</h2>
          <p class="mt-1 text-sm text-app-muted">Gerencie os clientes da conta ativa, sem misturá-los às contas do SAE.</p>
        </div>
        <form class="flex w-full flex-col gap-3 sm:flex-row lg:max-w-xl" @submit.prevent="handleSearch">
          <BaseInput v-model="search" placeholder="Buscar por razão social, fantasia ou CNPJ..." class="flex-1" />
          <BaseButton type="submit" variant="secondary" :disabled="store.isLoading"><PhMagnifyingGlass class="h-4 w-4" /> Buscar</BaseButton>
        </form>
        <SaeViewToggle v-model="viewMode" />
      </div>

      <BaseAlert v-if="store.error || pageError" class="mt-5" type="danger" :message="store.error || pageError" />
      <div v-if="notice" role="status" class="mt-5 rounded-xl border border-app-border bg-app-surface px-4 py-3 text-sm text-app-text">{{ notice }}</div>

      <div v-if="store.isLoading" class="flex min-h-80 items-center justify-center"><LoadingSpinner /></div>
      <div v-else-if="!hasCompanies" class="flex min-h-80 flex-col items-center justify-center text-center">
        <div class="flex h-16 w-16 items-center justify-center rounded-3xl bg-app-primary/10 text-app-primary"><PhBuildings class="h-8 w-8" /></div>
        <h3 class="mt-4 text-lg font-black text-app-text">Nenhuma empresa encontrada</h3>
        <p class="mt-2 max-w-md text-sm text-app-muted">Cadastre uma empresa para iniciar o acompanhamento de sua equipe.</p>
        <BaseButton class="mt-5" @click="openCreate"><PhPlus class="h-4 w-4" /> Cadastrar empresa</BaseButton>
      </div>
      <div v-else-if="viewMode === 'cards'" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <article v-for="company in companies" :key="keyOf(company)" class="sae-company-card rounded-2xl border border-app-border bg-app-surface p-5 shadow-sm">
          <div class="mb-4 flex items-start justify-between gap-3"><div class="min-w-0"><h3 class="break-words font-bold text-app-text">{{ company.trade_name || company.corporate_name }}</h3><p class="mt-1 text-xs text-app-muted">{{ company.corporate_name }}</p></div>
            <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="company.is_active ? 'bg-app-success/10 text-app-success' : 'bg-app-muted/10 text-app-muted'">{{ company.is_active ? 'Ativa' : 'Inativa' }}</span>
          </div>
          <dl class="space-y-2 text-sm"><div class="flex justify-between gap-2"><dt class="text-app-muted">CNPJ</dt><dd class="text-app-text">{{ cnpjDisplay(company.cnpj) }}</dd></div><div class="flex justify-between gap-2"><dt class="text-app-muted">Grau de risco</dt><dd class="font-semibold text-app-text">{{ company.risk_grade }}</dd></div></dl>
          <div class="mt-5 flex flex-wrap gap-2 border-t border-app-border pt-4"><BaseButton size="sm" @click="openTeam(company)">Equipe</BaseButton><BaseButton size="sm" variant="secondary" @click="openEdit(company)">Editar</BaseButton><BaseButton size="sm" variant="secondary" @click="handleArchive(company)">Arquivar</BaseButton></div>
        </article>
      </div>
      <div v-else class="sae-companies-table mt-5 overflow-hidden rounded-2xl border border-app-border">
        <div class="hidden overflow-x-auto lg:block">
          <table class="min-w-full divide-y divide-app-border">
            <thead class="bg-app-surface"><tr>
              <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wider text-app-muted">Empresa</th>
              <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wider text-app-muted">CNPJ</th>
              <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wider text-app-muted">Risco</th>
              <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wider text-app-muted">Situação</th>
              <th class="px-5 py-4 text-right text-xs font-black uppercase tracking-wider text-app-muted">Ações</th>
            </tr></thead>
            <tbody class="divide-y divide-app-border bg-app-bg">
              <tr v-for="company in companies" :key="keyOf(company)" class="sae-company-row transition hover:bg-app-surface/70">
                <td class="px-5 py-4"><p class="font-bold text-app-text">{{ company.corporate_name }}</p><p v-if="company.trade_name" class="mt-1 text-xs text-app-muted">{{ company.trade_name }}</p></td>
                <td class="px-5 py-4 text-sm text-app-muted">{{ cnpjDisplay(company.cnpj) }}</td>
                <td class="px-5 py-4 text-sm text-app-muted">Grau {{ company.risk_grade }}</td>
                <td class="px-5 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-black" :class="company.is_active ? 'bg-app-success/10 text-app-success' : 'bg-app-muted/10 text-app-muted'">{{ company.is_active ? 'Ativa' : 'Inativa' }}</span></td>
                <td class="px-5 py-4"><div class="flex justify-end gap-2">
                  <button type="button" class="action-button hover:text-app-primary" title="Cadastrar equipe" @click="openTeam(company)"><PhUsersThree class="h-4 w-4" /></button>
                  <button type="button" class="action-button hover:text-app-primary" title="Editar" @click="openEdit(company)"><PhPencilSimple class="h-4 w-4" /></button>
                  <button type="button" class="action-button hover:text-app-danger" title="Arquivar" @click="handleArchive(company)"><PhArchive class="h-4 w-4" /></button>
                </div></td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="divide-y divide-app-border lg:hidden">
          <article v-for="company in companies" :key="keyOf(company)" class="p-4">
            <div class="flex items-start justify-between gap-3"><div><h3 class="font-black text-app-text">{{ company.corporate_name }}</h3><p class="mt-1 text-sm text-app-muted">{{ cnpjDisplay(company.cnpj) }}</p><p class="mt-1 text-sm text-app-muted">Grau {{ company.risk_grade }} · {{ company.trade_name || 'Sem nome fantasia' }}</p></div>
              <span class="rounded-full px-2.5 py-1 text-xs font-black" :class="company.is_active ? 'bg-app-success/10 text-app-success' : 'bg-app-muted/10 text-app-muted'">{{ company.is_active ? 'Ativa' : 'Inativa' }}</span>
            </div>
            <div class="mt-4 flex flex-wrap gap-2"><BaseButton size="sm" variant="secondary" @click="openTeam(company)">Cadastrar equipe</BaseButton><BaseButton size="sm" variant="secondary" @click="openEdit(company)">Editar</BaseButton><BaseButton size="sm" variant="danger" @click="handleArchive(company)">Arquivar</BaseButton></div>
          </article>
        </div>
      </div>
      <div v-if="hasCompanies" class="mt-5 flex flex-col gap-3 border-t border-app-border pt-5 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-app-muted">Mostrando <strong class="text-app-text">{{ pagination.from }}</strong> até <strong class="text-app-text">{{ pagination.to }}</strong> de <strong class="text-app-text">{{ pagination.total }}</strong> registros.</p>
        <div class="flex items-center gap-2"><BaseButton size="sm" variant="secondary" :disabled="pagination.currentPage <= 1 || store.isLoading" @click="goToPage(pagination.currentPage - 1)">Anterior</BaseButton><span class="rounded-xl border border-app-border bg-app-surface px-3 py-2 text-sm font-bold text-app-muted">{{ pagination.currentPage }} / {{ pagination.lastPage }}</span><BaseButton size="sm" variant="secondary" :disabled="pagination.currentPage >= pagination.lastPage || store.isLoading" @click="goToPage(pagination.currentPage + 1)">Próxima</BaseButton></div>
      </div>
    </ContentCard>
    <ManagedCompanyDrawer :open="isDrawerOpen" :company="editingCompany" :is-saving="store.isSaving" :error="store.error" @close="closeDrawer" @submit="handleSubmit" />
  </section>
</template>

<style scoped>
.action-button { display:inline-flex; width:2.25rem; height:2.25rem; align-items:center; justify-content:center; border:1px solid rgb(var(--color-border)); border-radius:.75rem; background:rgb(var(--color-surface)); color:rgb(var(--color-muted)); transition:color .15s; }
</style>

<style scoped>
.sae-company-card {background:rgb(var(--color-surface-strong));border-top:3px solid rgb(var(--color-primary) / .70);box-shadow:0 5px 18px rgb(0 0 0 / .045);transition:transform .15s,border-color .15s;}
.sae-company-card:hover { transform:translateY(-2px);border-color:rgb(var(--color-primary) / .55); }
.sae-companies-table thead { background:rgb(var(--color-primary-soft) / .45); }
.sae-company-row:hover { background:rgb(var(--color-primary-soft) / .28); }
</style>
