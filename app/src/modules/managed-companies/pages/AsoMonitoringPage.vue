<script setup>
import { onMounted, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import PageHeader from '@/shared/components/layout/PageHeader.vue'
import ContentCard from '@/shared/components/cards/ContentCard.vue'
import BaseButton from '@/shared/components/buttons/BaseButton.vue'
import { asoMonitoringService } from '../services/asoOperations.service'

const router = useRouter()
const report = ref(null)
const loading = ref(false)
const error = ref('')
const search = ref('')
const companies = computed(() => (report.value?.companies ?? []).filter(x =>
  x.company.name.toLowerCase().includes(search.value.trim().toLowerCase())))
const totals = computed(() => companies.value.reduce((sum, c) => {
  for (const k of Object.keys(sum)) sum[k] += c.counts[k] || 0
  return sum
}, { active: 0, away: 0, upcoming: 0, overdue: 0, without_aso: 0, transmission_pending: 0 }))
async function load() {
  loading.value = true; error.value = ''
  try { const result = await asoMonitoringService.report(); report.value = result?.data?.companies ? result.data : result }
  catch (e) { error.value = e?.response?.data?.message || e.message || 'Erro ao carregar acompanhamento.' }
  finally { loading.value = false }
}
onMounted(load)
</script>
<template>
  <section>
    <PageHeader eyebrow="Gestão ocupacional" title="Acompanhamento de ASOs" description="Indicadores por empresa, calculados a partir dos vínculos vigentes e exames cadastrados.">
      <template #actions><BaseButton variant="secondary" @click="load">Atualizar</BaseButton></template>
    </PageHeader>
    <ContentCard>
      <div v-if="error" class="mb-4 text-sm text-red-500">{{ error }}</div>
      <div v-if="loading" class="py-8 text-app-muted">Carregando...</div>
      <template v-else>
        <div class="mb-5 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
          <div v-for="(label,key) in {active:'Ativos',away:'Afastados',upcoming:'A vencer',overdue:'Vencidos',without_aso:'Sem dados',transmission_pending:'Não enviados'}" :key="key" class="rounded-xl border border-app-border p-3">
            <p class="text-xs text-app-muted">{{label}}</p><p class="mt-2 text-2xl font-bold text-app-text">{{ totals[key] }}</p>
          </div>
        </div>
        <label class="mb-4 block text-sm text-app-text">Pesquisar empresa
          <input v-model="search" class="mt-1 w-full rounded-lg border border-app-border bg-app-bg p-3 text-app-text" placeholder="Razão social ou fantasia" />
        </label>
        <div class="overflow-x-auto"><table class="min-w-full divide-y divide-app-border text-sm text-app-text">
          <thead><tr class="text-left text-app-muted"><th class="p-3">Empresa</th><th class="p-3">Ativos</th><th class="p-3">A vencer</th><th class="p-3">Vencidos</th><th class="p-3">Sem dados</th><th class="p-3">Não enviados</th><th class="p-3">Ação</th></tr></thead>
          <tbody class="divide-y divide-app-border"><tr v-for="item in companies" :key="item.company.uuid">
            <td class="p-3 font-semibold">{{item.company.name}}</td><td class="p-3">{{item.counts.active}}</td><td class="p-3">{{item.counts.upcoming}}</td><td class="p-3">{{item.counts.overdue}}</td><td class="p-3">{{item.counts.without_aso}}</td><td class="p-3">{{item.counts.transmission_pending}}</td>
            <td class="p-3"><BaseButton size="sm" variant="secondary" @click="router.push({name:'managed-company-team',params:{companyUuid:item.company.uuid}})">Equipe</BaseButton></td>
          </tr></tbody>
        </table></div>
        <p v-if="!companies.length" class="py-5 text-sm text-app-muted">Nenhuma empresa encontrada.</p>
        <p class="mt-4 text-xs text-app-muted">Acompanhamento provisório: depende da data de próximo vencimento informada no cadastro do ASO. Não representa automaticamente a situação de saúde do trabalhador.</p>
      </template>
    </ContentCard>
  </section>
</template>
