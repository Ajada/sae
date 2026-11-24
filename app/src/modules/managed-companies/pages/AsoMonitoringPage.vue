<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import PageHeader from '@/shared/components/layout/PageHeader.vue'
import ContentCard from '@/shared/components/cards/ContentCard.vue'
import BaseButton from '@/shared/components/buttons/BaseButton.vue'
import SaeViewToggle from '../components/SaeViewToggle.vue'
import { asoMonitoringService } from '../services/asoOperations.service'

const router = useRouter()
const report = ref(null)
const loading = ref(false)
const error = ref('')
const search = ref('')
const situation = ref('all')
const viewMode = ref('table') // padrão solicitado: tabela
const sortBy = ref('critical')
const numbers = ['active','away','upcoming','overdue','without_aso','transmission_pending']
const labels = {active:'Ativos',away:'Afastados',upcoming:'A vencer',overdue:'Vencidos',without_aso:'Sem ASO/dados',transmission_pending:'Não transmitidos'}
const allCompanies = computed(() => report.value?.companies ?? [])
const globalTotals = computed(() => allCompanies.value.reduce((sum, company) => {
  for (const key of numbers) sum[key] += Number(company.counts?.[key] || 0)
  return sum
}, {active:0,away:0,upcoming:0,overdue:0,without_aso:0,transmission_pending:0}))
const companies = computed(() => {
  const term = search.value.trim().toLocaleLowerCase('pt-BR')
  return allCompanies.value.filter(item => {
    const c = item.counts || {}
    if (!item.company.name.toLocaleLowerCase('pt-BR').includes(term)) return false
    if (situation.value === 'overdue') return c.overdue > 0
    if (situation.value === 'upcoming') return c.upcoming > 0
    if (situation.value === 'without_aso') return c.without_aso > 0
    if (situation.value === 'transmission_pending') return c.transmission_pending > 0
    return true
  }).sort((a,b) => {
    if (sortBy.value === 'name') return a.company.name.localeCompare(b.company.name,'pt-BR')
    const score = x => Number(x.counts?.overdue || 0) * 100000 + Number(x.counts?.upcoming || 0) * 100 + Number(x.counts?.without_aso || 0)
    return score(b) - score(a) || a.company.name.localeCompare(b.company.name,'pt-BR')
  })
})
const priority = computed(() => [...allCompanies.value].sort((a,b) => {
  const aScore = (a.counts?.overdue || 0) * 100000 + (a.counts?.upcoming || 0)
  const bScore = (b.counts?.overdue || 0) * 100000 + (b.counts?.upcoming || 0)
  return bScore-aScore
}).filter(c => (c.counts?.overdue || 0) + (c.counts?.upcoming || 0) > 0).slice(0,5))
async function load() {
  loading.value = true; error.value = ''
  try {
    const response = await asoMonitoringService.report()
    const payload = response?.data?.companies ? response.data : response
    if (!Array.isArray(payload?.companies)) throw new Error('Resposta de acompanhamento inválida.')
    report.value = payload
  } catch(e) { error.value = e?.response?.data?.message || e?.message || 'Falha ao carregar ASOs.' }
  finally { loading.value = false }
}
function openCompany(item) {
  router.push({ name:'aso-company', params:{ companyUuid:item.company.uuid } })
}
onMounted(load)
</script>
<template>
  <section>
    <PageHeader eyebrow="Gestão ocupacional" title="ASOs — Visão geral"
      description="Acompanhe as empresas e concentre as ações de gestão antes de abrir a equipe.">
      <template #actions><BaseButton variant="secondary" :disabled="loading" @click="load">Atualizar</BaseButton></template>
    </PageHeader>
    <p v-if="error" role="alert" class="mb-4 rounded-lg border border-red-400 p-3 text-sm text-red-500">{{ error }}</p>
    <ContentCard>
      <p v-if="loading" class="py-8 text-sm text-app-muted" role="status">Atualizando os indicadores...</p>
      <template v-else-if="report">
        <div class="mb-4 flex flex-wrap items-baseline justify-between gap-3">
          <div><h2 class="font-semibold text-app-text">Resumo da conta</h2><p class="text-xs text-app-muted">{{ allCompanies.length }} empresa(s) · referência {{ report.date || '—' }} · janela {{ report.alert_days ?? 30 }} dias</p></div>
          <span class="text-xs text-app-muted">Indicadores de vínculos vigentes</span>
        </div>
        <div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
          <div v-for="key in numbers" :key="key" class="rounded-xl border border-app-border bg-app-surface p-3">
            <p class="text-xs text-app-muted">{{ labels[key] }}</p><p class="mt-2 text-2xl font-bold text-app-text">{{ globalTotals[key] }}</p>
          </div>
        </div>
        <div v-if="priority.length" class="mb-6 rounded-xl border border-app-border bg-app-surface p-4">
          <h3 class="mb-1 font-semibold text-app-text">Prioridades identificadas</h3>
          <p class="mb-3 text-xs text-app-muted">Ordenadas por ASOs vencidos e, depois, a vencer. Não é um ranking de desempenho.</p>
          <div class="grid gap-2 md:grid-cols-2 xl:grid-cols-5">
            <button v-for="item in priority" :key="item.company.uuid" type="button"
              class="rounded-lg border border-app-border p-3 text-left hover:bg-app-surface" @click="openCompany(item)">
              <p class="truncate text-sm font-semibold text-app-text" :title="item.company.name">{{ item.company.name }}</p>
              <p class="mt-2 text-xs text-app-muted">{{ item.counts.overdue }} vencidos · {{ item.counts.upcoming }} a vencer</p>
            </button>
          </div>
        </div>
        <div class="mb-4 flex flex-wrap items-end gap-3">
          <label class="min-w-48 flex-1 text-xs font-medium text-app-text">Pesquisar empresa
            <input v-model="search" type="search" placeholder="Razão social ou nome fantasia"
              class="mt-2 w-full rounded-lg border border-app-border bg-app-bg p-3 text-sm text-app-text" />
          </label>
          <label class="text-xs font-medium text-app-text">Pendência
            <select v-model="situation" class="mt-2 block rounded-lg border border-app-border bg-app-bg p-3 text-sm text-app-text">
              <option value="all">Todas</option><option value="overdue">Com vencidos</option><option value="upcoming">A vencer</option>
              <option value="without_aso">Sem ASO/dados</option><option value="transmission_pending">Não transmitidos</option>
            </select>
          </label>
          <label class="text-xs font-medium text-app-text">Ordenar
            <select v-model="sortBy" class="mt-2 block rounded-lg border border-app-border bg-app-bg p-3 text-sm text-app-text">
              <option value="critical">Mais pendências</option><option value="name">Nome</option>
            </select>
          </label>
          <SaeViewToggle v-model="viewMode" />
        </div>
        <div v-if="!companies.length" class="py-10 text-center text-sm text-app-muted">Nenhuma empresa encontrada para os filtros selecionados.</div>
        <div v-else-if="viewMode === 'table'" class="overflow-x-auto rounded-lg border border-app-border">
          <table class="min-w-full divide-y divide-app-border text-sm text-app-text">
            <thead class="text-left text-xs text-app-muted"><tr>
              <th class="p-3">Empresa</th><th class="p-3">Ativos</th><th class="p-3">Afastados</th>
              <th class="p-3">A vencer</th><th class="p-3">Vencidos</th><th class="p-3">Sem dados</th>
              <th class="p-3">Não transmitidos</th><th class="p-3">Ações</th>
            </tr></thead>
            <tbody class="divide-y divide-app-border">
              <tr v-for="item in companies" :key="item.company.uuid">
                <td class="p-3 font-semibold">{{ item.company.name }}</td>
                <td class="p-3">{{ item.counts.active }}</td><td class="p-3">{{ item.counts.away }}</td>
                <td class="p-3">{{ item.counts.upcoming }}</td><td class="p-3">{{ item.counts.overdue }}</td>
                <td class="p-3">{{ item.counts.without_aso }}</td><td class="p-3">{{ item.counts.transmission_pending }}</td>
                <td class="p-3"><BaseButton size="sm" variant="secondary" @click="openCompany(item)">Gerenciar ASOs</BaseButton></td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
          <article v-for="item in companies" :key="item.company.uuid" class="rounded-xl border border-app-border bg-app-surface p-4">
            <h3 class="mb-3 truncate font-semibold text-app-text" :title="item.company.name">{{ item.company.name }}</h3>
            <div class="mb-4 grid grid-cols-2 gap-3 text-sm">
              <div v-for="key in numbers" :key="key"><p class="text-xs text-app-muted">{{ labels[key] }}</p><p class="font-semibold text-app-text">{{ item.counts[key] ?? 0 }}</p></div>
            </div>
            <BaseButton size="sm" variant="secondary" @click="openCompany(item)">Gerenciar ASOs</BaseButton>
          </article>
        </div>
        <p class="mt-5 text-xs text-app-muted">Vencimentos dependem de datas de ASOs já registradas; sem informação suficiente, o SAE mostra “Sem ASO/dados”. Indicadores não incluem vínculos desligados.</p>
      </template>
    </ContentCard>
  </section>
</template>
