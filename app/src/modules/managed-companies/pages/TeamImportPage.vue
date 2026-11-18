<script setup>
import { computed, ref, reactive, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { PhArrowLeft, PhFileXls, PhUploadSimple, PhCheckCircle } from '@phosphor-icons/vue'
import PageHeader from '@/shared/components/layout/PageHeader.vue'
import ContentCard from '@/shared/components/cards/ContentCard.vue'
import BaseButton from '@/shared/components/buttons/BaseButton.vue'
import BaseAlert from '@/shared/components/feedback/BaseAlert.vue'
import { teamImportService } from '../services/teamImport.service'

const route = useRoute()
const router = useRouter()
const companyUuid = computed(() => String(route.params.companyUuid))
const file = ref(null)
const busy = ref(false)
const error = ref('')
const uploaded = ref(null)
const preview = ref(null)
const finished = ref(null)
const mapping = reactive({})
const enabled = reactive({})
const query = ref('')
const filter = ref('all')
const page = ref(1)
const pageSize = 30
const fieldLabels = [
  ['name','Nome *'],['cpf','CPF *'],['registration','Matrícula'],
  ['job_title','Função'],['department','Setor'],['admitted_at','Admissão *'],
  ['terminated_at','Demissão'],['situation','Situação'],
]
const statuses = { new_employee:'Nova pessoa + vínculo', new_link:'Novo vínculo', existing:'Já cadastrado', review:'Revisão necessária' }
const eligible = row => row.status === 'new_employee' || row.status === 'new_link'
const rows = computed(() => preview.value?.rows || [])
const filtered = computed(() => rows.value.filter(row =>
  (filter.value === 'all' || row.status === filter.value) &&
  (!query.value.trim() || `${row.data.name} ${row.data.cpf} ${row.data.registration || ''}`.toLowerCase().includes(query.value.toLowerCase().trim()))
))
const pages = computed(() => Math.max(1, Math.ceil(filtered.value.length / pageSize)))
const shown = computed(() => filtered.value.slice((page.value-1)*pageSize, page.value*pageSize))
const selected = computed(() => rows.value.filter(r => enabled[r.row] && eligible(r)).map(r => r.row))
watch([query,filter], () => { page.value = 1 })
const unwrap = response => response?.data ?? response
const getError = e => {
  const data = e?.response?.data
  return data?.errors ? Object.values(data.errors).flat().join(' ') : data?.message || e?.message || 'Erro durante a importação.'
}
function pick(e) { file.value = e.target.files?.[0] || null; uploaded.value = null; preview.value = null; finished.value = null; error.value = '' }
async function upload() {
  if (!file.value) return
  busy.value = true; error.value = ''
  try {
    const data = unwrap(await teamImportService.upload(companyUuid.value, file.value))
    if (!data?.batch_uuid) throw new Error('A API não retornou o identificador do lote.')
    uploaded.value = data
    Object.keys(mapping).forEach(k => delete mapping[k])
    Object.assign(mapping, data.suggested_mapping)
  } catch(e) { error.value = getError(e) }
  finally { busy.value = false }
}
async function analyze() {
  busy.value = true; error.value = ''
  try {
    const result = unwrap(await teamImportService.preview(companyUuid.value, uploaded.value.batch_uuid, { ...mapping }))
    if (!Array.isArray(result?.rows)) throw new Error('A API não retornou as linhas de conferência.')
    preview.value = result
    Object.keys(enabled).forEach(k => delete enabled[k])
    for (const row of result.rows) enabled[row.row] = eligible(row)
    page.value = 1
  } catch(e) { error.value = getError(e) }
  finally { busy.value = false }
}
function toggleFiltered(value) { for (const row of filtered.value) if (eligible(row)) enabled[row.row] = value }
async function commit() {
  if (!selected.value.length) { error.value = 'Selecione pelo menos uma linha válida.'; return }
  if (!window.confirm(`Confirmar a importação de ${selected.value.length} vínculos? Os ASOs não serão importados nesta etapa.`)) return
  busy.value = true; error.value = ''
  try {
    const result = unwrap(await teamImportService.commit(companyUuid.value, uploaded.value.batch_uuid, selected.value))
    finished.value = result
  } catch(e) { error.value = getError(e) }
  finally { busy.value = false }
}
</script>

<template>
  <section>
    <PageHeader eyebrow="Empresas / Equipe" title="Importar colaboradores" description="Envie a planilha, confira o mapeamento e escolha os registros antes de salvar.">
      <template #actions><BaseButton variant="secondary" @click="router.push({name:'managed-company-team',params:{companyUuid}})"><PhArrowLeft class="h-4 w-4"/> Voltar à equipe</BaseButton></template>
    </PageHeader>
    <BaseAlert v-if="error" class="mb-4" type="danger" :message="error" />
    <ContentCard v-if="finished">
      <div class="flex items-center gap-3 text-app-text"><PhCheckCircle class="h-9 w-9 text-app-success"/><h2 class="text-xl font-black">Importação concluída</h2></div>
      <p class="mt-3 text-sm text-app-muted">{{ finished.result?.created_employees ?? 0 }} colaboradores novos, {{ finished.result?.created_links ?? 0 }} vínculos criados e {{ finished.result?.ignored ?? 0 }} linhas ignoradas.</p>
      <p class="mt-2 text-sm text-app-muted">Nenhum ASO foi gravado. O histórico de exames será habilitado na próxima etapa.</p>
      <BaseButton class="mt-5" @click="router.push({name:'managed-company-team',params:{companyUuid}})">Ver equipe</BaseButton>
    </ContentCard>
    <template v-else>
      <ContentCard>
        <h2 class="text-lg font-black text-app-text">1. Selecionar arquivo</h2>
        <p class="mt-1 text-sm text-app-muted">Formatos XLSX, XLS ou CSV. Primeira aba, até 5.000 linhas e arquivo de até 10 MB.</p>
        <div class="mt-4 flex flex-wrap items-center gap-3">
          <label class="flex max-w-full cursor-pointer items-center gap-2 rounded-xl border border-app-border bg-app-surface px-4 py-3 text-sm text-app-text"><PhFileXls class="h-5 w-5"/><span class="max-w-64 truncate">{{ file?.name || 'Escolher Excel' }}</span><input type="file" accept=".xlsx,.xls,.csv" class="sr-only" @change="pick"/></label>
          <BaseButton :disabled="!file || busy" @click="upload"><PhUploadSimple class="h-4 w-4"/> {{ busy ? 'Processando...' : 'Analisar arquivo' }}</BaseButton>
        </div>
        <p v-if="uploaded" class="mt-3 text-sm text-app-muted">Aba: {{ uploaded.sheet }} · {{ uploaded.records_count }} linhas localizadas · Prévia válida por 24 horas.</p>
      </ContentCard>
      <ContentCard v-if="uploaded" class="mt-4">
        <h2 class="text-lg font-black text-app-text">2. Conferir colunas</h2>
        <p class="mt-1 text-sm text-app-muted">O SAE sugeriu as correspondências abaixo. Ajuste antes de processar. Nome, CPF e admissão são obrigatórios nesta versão.</p>
        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <label v-for="[field,label] in fieldLabels" :key="field" class="text-sm font-bold text-app-text">{{ label }}
            <select v-model="mapping[field]" class="sae-import-input mt-2"><option :value="null">Não informado</option><option v-for="(header,index) in uploaded.headers" :key="index" :value="index">{{ String.fromCharCode(65+index) }} — {{ header || '(sem título)' }}</option></select>
          </label>
        </div>
        <p class="mt-4 text-sm font-bold text-app-text">Colunas de ASO detectadas (somente prévia)</p>
        <div class="mt-2 flex flex-wrap gap-3"><label v-for="(header,index) in uploaded.headers" :key="index" class="flex items-center gap-2 text-sm text-app-muted"><template v-if="mapping.aso_columns?.includes(index)"><input type="checkbox" checked @change="mapping.aso_columns = $event.target.checked ? [...mapping.aso_columns, index] : mapping.aso_columns.filter(i=>i!==index)"/>{{ header }}</template></label></div>
        <BaseButton class="mt-5" :disabled="busy" @click="analyze">{{ busy ? 'Conferindo...' : 'Gerar prévia de conferência' }}</BaseButton>
      </ContentCard>
      <ContentCard v-if="preview" class="mt-4">
        <h2 class="text-lg font-black text-app-text">3. Conferência antes de salvar</h2>
        <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-5">
          <div v-for="[key,label] in [['total','Linhas'],['new_employee','Novas pessoas'],['new_link','Novos vínculos'],['existing','Já cadastrados'],['review','Revisar']]" :key="key" class="rounded-xl border border-app-border bg-app-surface p-3"><p class="text-xs text-app-muted">{{ label }}</p><p class="text-2xl font-black text-app-text">{{ preview.summary[key] }}</p></div>
        </div>
        <p class="mt-4 rounded-xl border border-app-border bg-app-surface px-4 py-3 text-sm text-app-muted">{{ preview.aso_note }} Exames marcados como PENDENTE, NA e A VENCER não serão convertidos em datas.</p>
        <div class="mt-4 flex flex-wrap items-center gap-3">
          <input v-model="query" class="sae-import-input max-w-xs" placeholder="Buscar nome, CPF ou matrícula"/>
          <select v-model="filter" class="sae-import-input max-w-xs"><option value="all">Todos</option><option v-for="(label,key) in statuses" :key="key" :value="key">{{ label }}</option></select>
          <button class="text-sm font-bold text-app-text underline" @click="toggleFiltered(true)">Selecionar válidos do filtro</button>
          <button class="text-sm font-bold text-app-muted underline" @click="toggleFiltered(false)">Desmarcar do filtro</button>
        </div>
        <div class="mt-4 overflow-x-auto rounded-xl border border-app-border">
          <table class="min-w-full divide-y divide-app-border text-sm">
            <thead class="bg-app-surface text-left text-xs uppercase text-app-muted"><tr><th class="p-3">Importar</th><th class="p-3">Linha</th><th class="p-3">Nome / CPF</th><th class="p-3">Matrícula / admissão</th><th class="p-3">Análise</th><th class="p-3">ASOs identificados</th></tr></thead>
            <tbody class="divide-y divide-app-border"><tr v-for="row in shown" :key="row.row" class="text-app-text">
              <td class="p-3"><input v-model="enabled[row.row]" type="checkbox" :disabled="!eligible(row)"/></td>
              <td class="p-3">{{ row.row }}</td>
              <td class="p-3"><b>{{ row.data.name || '—' }}</b><p class="text-xs text-app-muted">{{ row.data.cpf }}</p></td>
              <td class="p-3">{{ row.data.registration || 'Sem matrícula' }}<p class="text-xs text-app-muted">{{ row.data.admitted_at || 'Sem admissão' }}</p></td>
              <td class="p-3"><span class="font-bold">{{ statuses[row.status] }}</span><p v-for="issue in row.issues" :key="issue" class="text-xs text-app-muted">{{ issue }}</p></td>
              <td class="p-3"><span v-if="!row.aso_preview.length" class="text-app-muted">—</span><details v-else><summary class="cursor-pointer text-app-primary">{{ row.aso_preview.length }} registro(s)</summary><p v-for="(aso,i) in row.aso_preview" :key="i" class="mt-1 text-xs">{{ aso.column }}: {{ aso.value }}</p></details></td>
            </tr></tbody>
          </table>
        </div>
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm text-app-muted"><span>Exibindo {{ shown.length }} de {{ filtered.length }} linhas filtradas.</span><div class="flex items-center gap-3"><button :disabled="page===1" @click="page--">Anterior</button><span>{{ page }} / {{ pages }}</span><button :disabled="page===pages" @click="page++">Próxima</button></div></div>
        <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-app-border pt-4"><p class="text-sm font-bold text-app-text">{{ selected.length }} vínculos selecionados para inclusão</p><BaseButton :disabled="busy || !selected.length" @click="commit">{{ busy ? 'Salvando...' : 'Confirmar importação' }}</BaseButton></div>
      </ContentCard>
    </template>
  </section>
</template>
<style scoped>
.sae-import-input { display:block; width:100%; border:1px solid rgb(var(--color-border)); background:rgb(var(--color-bg)); color:rgb(var(--color-text)); border-radius:.75rem; padding:.7rem .85rem; font-size:.875rem; outline:none; }
.sae-import-input:focus { border-color:rgb(var(--color-primary)); }
</style>
