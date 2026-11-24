<script setup>
import { computed, ref, reactive, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { PhArrowLeft, PhFileXls, PhUploadSimple, PhCheckCircle } from '@phosphor-icons/vue'
import PageHeader from '@/shared/components/layout/PageHeader.vue'
import ContentCard from '@/shared/components/cards/ContentCard.vue'
import BaseButton from '@/shared/components/buttons/BaseButton.vue'
import BaseAlert from '@/shared/components/feedback/BaseAlert.vue'
import { teamImportService } from '../services/teamImport.service'
import TeamImportRowModal from '../components/TeamImportRowModal.vue'

const route = useRoute()
const router = useRouter()
const companyUuid = computed(() => String(route.params.companyUuid))
const file = ref(null)
const busy = ref(false)
const error = ref('')
const uploaded = ref(null)
const preview = ref(null)
const finished = ref(null)
const asoPreview = ref(null)
const asoResult = ref(null)
const periodicityMonths = ref(12)
const asoSelected = reactive({})
const asoFileConfirmed = ref(false)
const openHistory = reactive({})
const asoByLine = computed(() => {
  const groups = {}
  for (const item of asoPreview.value?.rows || []) (groups[item.row] ||= []).push(item)
  return groups
})
const historyFor = row => asoByLine.value[row.row] || []
const ignoredStatesFor = row => (row.aso_preview || []).filter(a => /^(na|n\/a|não aplicável|nao aplicavel|pendente|a vencer|dispensado|verificar|-)\b/i.test(String(a.value).trim()) || String(a.value).trim() === '-')
const historyCount = row => Math.max(historyFor(row).length + (asoPreview.value ? ignoredStatesFor(row).length : 0), row.aso_preview?.length || 0)
function toggleHistory(row) { openHistory[row.row] = !openHistory[row.row] }
const replacementAcknowledged = ref(false)
const asoNewRows = computed(() => asoPreview.value?.rows?.filter(r => r.status === 'new') || [])
const selectedAsoKeys = computed(() => asoNewRows.value.filter(r => asoSelected[r.key]).map(r => r.key))
const canConfirmAsos = computed(() => !!finished.value || (!!preview.value && selected.value.length === 0))
async function previewAsos() {
  if (!uploaded.value) return
  busy.value = true; error.value = ''; asoResult.value = null
  try {
    const result = unwrap(await teamImportService.asoPreview(companyUuid.value, uploaded.value.batch_uuid, Number(periodicityMonths.value)))
    asoPreview.value = result
    Object.keys(asoSelected).forEach(k => delete asoSelected[k])
    for (const row of result.rows) if (row.status === 'new') asoSelected[row.key] = true
    asoFileConfirmed.value = false
  } catch (e) { error.value = getError(e) }
  finally { busy.value = false }
}
async function commitAsos() {
  if (!asoFileConfirmed.value || !selectedAsoKeys.value.length) return
  if (!window.confirm(`Confirma a importação de ${selectedAsoKeys.value.length} ASOs do arquivo ${file.value?.name || uploaded.value?.original_name || "selecionado"}?`)) return
  busy.value = true; error.value = ''
  try {
    const result = unwrap(await teamImportService.asoCommit(companyUuid.value, uploaded.value.batch_uuid, {
      keys: selectedAsoKeys.value,
      periodicity_months: Number(periodicityMonths.value),
      file_sha256: uploaded.value.sha256,
    }))
    asoResult.value = result
    await previewAsos()
    asoResult.value = result
  } catch(e) { error.value = getError(e) }
  finally { busy.value = false }
}

const mapping = reactive({})
const enabled = reactive({})
const query = ref('')
const filter = ref('all')
const editing = ref(null)
const editingRow = computed(() => rows.value.find(row => row.row === editing.value) || null)
const correctionError = ref('')
function editRow(row) {
 correctionError.value = ''
 editing.value = row.row
}
async function saveCorrection(data) {
 if (editing.value === null || busy.value) return
 busy.value = true; correctionError.value = ''; error.value = ''
 try {
   const result = unwrap(await teamImportService.correct(companyUuid.value, uploaded.value.batch_uuid, editing.value, data))
   preview.value = result
   Object.keys(enabled).forEach(k => { if (!result.rows.some(r => String(r.row) === k && eligible(r))) enabled[k] = false })
   for (const row of result.rows) if (row.row === editing.value && eligible(row)) enabled[row.row] = true
   editing.value = null
 } catch(e) { correctionError.value = getError(e) }
 finally { busy.value = false }
}

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
function pick(e) { file.value = e.target.files?.[0] || null; uploaded.value = null; preview.value = null; finished.value = null; asoPreview.value = null; asoResult.value = null; Object.keys(openHistory).forEach(k => delete openHistory[k]); error.value = '' }
async function upload() {
  if (!file.value) return
  busy.value = true; error.value = ''
  try {
    const data = unwrap(await teamImportService.upload(companyUuid.value, file.value))
    if (!data?.batch_uuid) throw new Error('A API não retornou o identificador do lote.')
    uploaded.value = data
    replacementAcknowledged.value = !data.previous_import
    Object.keys(mapping).forEach(k => delete mapping[k])
    Object.assign(mapping, data.suggested_mapping)
    preview.value = null; finished.value = null; asoPreview.value = null
  } catch(e) { error.value = getError(e) }
  finally { busy.value = false }
}
async function analyze() {
  busy.value = true; error.value = ''
  try {
    const result = unwrap(await teamImportService.preview(companyUuid.value, uploaded.value.batch_uuid, { ...mapping }))
    if (!Array.isArray(result?.rows)) throw new Error('A API não retornou as linhas de conferência.')
    preview.value = result
    asoPreview.value = null // Reprocessar a conferência dos exames após alterar identificação/vínculo.
    Object.keys(enabled).forEach(k => delete enabled[k])
    for (const row of result.rows) enabled[row.row] = eligible(row)
    page.value = 1
    Object.keys(openHistory).forEach(k => delete openHistory[k])
  } catch(e) { error.value = getError(e) }
  finally { busy.value = false }
}
function toggleFiltered(value) { for (const row of filtered.value) if (eligible(row)) enabled[row.row] = value }
async function commit() {
  if (!selected.value.length) { error.value = 'Selecione pelo menos uma linha válida.'; return }
  if (!window.confirm(`Confirmar a importação de ${selected.value.length} vínculos? Os ASOs serão conferidos logo depois, na mesma tabela.`)) return
  busy.value = true; error.value = ''
  try {
    const result = unwrap(await teamImportService.commit(companyUuid.value, uploaded.value.batch_uuid, selected.value))
    finished.value = result
    // Agora os vínculos existem no banco; atualizar a conferência dos ASOs na mesma tela.
    await previewAsos()
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
      <p class="mt-2 text-sm text-app-muted">Os ASOs são conferidos dentro do histórico de cada colaborador, na tabela abaixo.</p>
      <BaseButton class="mt-5" @click="router.push({name:'managed-company-team',params:{companyUuid}})">Ver equipe</BaseButton>
    </ContentCard>
    <template v-if="!finished">
      <ContentCard>
        <h2 class="text-lg font-black text-app-text">1. Selecionar arquivo</h2>
        <p class="mt-1 text-sm text-app-muted">Formatos XLSX, XLS ou CSV. Primeira aba, até 5.000 linhas e arquivo de até 10 MB.</p>
        <div class="mt-4 flex flex-wrap items-center gap-3">
          <label class="flex max-w-full cursor-pointer items-center gap-2 rounded-xl border border-app-border bg-app-surface px-4 py-3 text-sm text-app-text"><PhFileXls class="h-5 w-5"/><span class="max-w-64 truncate">{{ file?.name || 'Escolher Excel' }}</span><input type="file" accept=".xlsx,.xls,.csv" class="sr-only" @change="pick"/></label>
          <BaseButton :disabled="!file || busy" @click="upload"><PhUploadSimple class="h-4 w-4"/> {{ busy ? 'Processando...' : 'Analisar arquivo' }}</BaseButton>
        </div>
        <div v-if="uploaded?.previous_import" class="mt-4 rounded-xl border border-app-border bg-app-surface p-3 text-sm text-app-text">
          <p class="font-bold">Atualização de equipe já cadastrada</p>
          <p class="mt-1 text-app-muted">Anterior: {{ uploaded.previous_import.original_name }}</p>
          <p class="text-app-muted">Novo: {{ file?.name }}</p>
          <label class="mt-2 flex items-center gap-2"><input v-model="replacementAcknowledged" type="checkbox" /> Confirmei que esta planilha pertence à empresa selecionada.</label>
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
        <p class="mt-4 text-sm font-bold text-app-text">Colunas de ASO detectadas (incluídas na conferência)</p>
        <div class="mt-2 flex flex-wrap gap-3"><label v-for="(header,index) in uploaded.headers" :key="index" class="flex items-center gap-2 text-sm text-app-muted"><template v-if="mapping.aso_columns?.includes(index)"><input type="checkbox" checked @change="mapping.aso_columns = $event.target.checked ? [...mapping.aso_columns, index] : mapping.aso_columns.filter(i=>i!==index)"/>{{ header }}</template></label></div>
        <BaseButton class="mt-5" :disabled="busy || !replacementAcknowledged" @click="analyze">{{ busy ? 'Conferindo...' : 'Gerar prévia de conferência' }}</BaseButton>
      </ContentCard>
    </template>
    <ContentCard v-if="preview" class="mt-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div><h2 class="text-lg font-black text-app-text">3. Conferência integrada · equipe e ASOs</h2>
          <p class="mt-1 text-sm text-app-muted">Abra o histórico de cada colaborador. Após gravar os vínculos novos, confira e confirme os exames nesta mesma tabela.</p></div>
          <label class="text-xs font-semibold text-app-text">Periodicidade PCMSO (meses)
            <input v-model.number="periodicityMonths" class="sae-import-input mt-1 w-32" type="number" min="1" max="60" />
          </label>
        </div>
        <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-5">
          <div v-for="[key,label] in [['total','Linhas'],['new_employee','Novas pessoas'],['new_link','Novos vínculos'],['existing','Já cadastrados'],['review','Revisar']]" :key="key" class="rounded-xl border border-app-border bg-app-surface p-3"><p class="text-xs text-app-muted">{{ label }}</p><p class="text-2xl font-black text-app-text">{{ preview.summary[key] }}</p></div>
        </div>
        <div class="mt-4 rounded-xl border border-app-border bg-app-surface px-4 py-3 text-sm text-app-muted">
          Os ASOs da planilha são exibidos junto do colaborador. PENDENTE, NA e A VENCER não são exames realizados.
          <span v-if="!finished"> Primeiro confirme os vínculos novos; os exames serão reanalisados automaticamente.</span>
        </div>
        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
          <span class="text-xs text-app-muted">{{ asoPreview ? `${asoPreview.summary.new} ASOs novos · ${asoPreview.summary.existing} existentes · ${asoPreview.summary.review} a revisar` : 'ASOs ainda não conferidos no banco' }}</span>
          <BaseButton size="sm" variant="secondary" :disabled="busy" @click="previewAsos">{{ busy ? 'Analisando...' : 'Atualizar conferência dos ASOs' }}</BaseButton>
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-3">
          <input v-model="query" class="sae-import-input max-w-xs" placeholder="Buscar nome, CPF ou matrícula"/>
          <select v-model="filter" class="sae-import-input max-w-xs"><option value="all">Todos</option><option v-for="(label,key) in statuses" :key="key" :value="key">{{ label }}</option></select>
          <button class="text-sm font-bold text-app-text underline" @click="toggleFiltered(true)">Selecionar válidos do filtro</button>
          <button class="text-sm font-bold text-app-muted underline" @click="toggleFiltered(false)">Desmarcar do filtro</button>
        </div>
        <div class="mt-4 overflow-x-auto rounded-xl border border-app-border">
          <table class="w-full min-w-[780px] border-collapse text-left text-sm text-app-text">
            <thead class="bg-app-surface text-xs uppercase text-app-muted">
              <tr><th class="p-3">Importar</th><th class="p-3">Linha</th><th class="p-3">Colaborador / CPF</th><th class="p-3">Matrícula / admissão</th><th class="p-3">Análise</th><th class="p-3">Histórico Excel</th><th class="p-3">Ação</th></tr>
            </thead>
            <tbody>
              <template v-for="row in shown" :key="row.row">
                <tr class="border-t border-app-border hover:bg-app-surface/60">
                  <td class="p-3"><input v-model="enabled[row.row]" type="checkbox" :disabled="!!finished || !eligible(row)" /></td>
                  <td class="p-3 text-app-muted">{{ row.row }}</td>
                  <td class="p-3"><b>{{ row.data.name || '—' }}</b><p class="text-xs text-app-muted">{{ row.data.cpf }}</p></td>
                  <td class="p-3">{{ row.data.registration || 'Sem matrícula' }}<p class="text-xs text-app-muted">{{ row.data.admitted_at || 'Sem admissão' }}</p></td>
                  <td class="p-3"><span class="font-semibold">{{ statuses[row.status] }}</span><p v-for="issue in row.issues" :key="issue" class="text-xs text-app-danger">{{ issue }}</p></td>
                  <td class="p-3"><button type="button" class="sae-inline-action" :aria-expanded="!!openHistory[row.row]" @click="toggleHistory(row)">{{ openHistory[row.row] ? 'Ocultar' : 'Histórico' }} ({{ historyCount(row) }})</button></td>
                  <td class="p-3"><button type="button" class="sae-inline-action" :disabled="busy || !!finished" @click="editRow(row)">Corrigir</button></td>
                </tr>
                <tr v-if="openHistory[row.row]" class="border-t border-app-border bg-app-surface/70">
                  <td colspan="7" class="px-4 py-4 sm:px-6">
                    <div class="rounded-xl border border-app-border bg-app-bg p-4">
                      <div class="mb-3 flex flex-wrap items-center justify-between gap-2"><p class="font-bold">Histórico de ASOs · {{ row.data.name }}</p><span class="text-xs text-app-muted">{{ asoPreview ? 'Comparado com o banco' : 'Prévia do Excel' }}</span></div>
                      <div v-if="!historyCount(row)" class="text-sm text-app-muted">Nenhum ASO identificado nesta linha.</div>
                      <div v-else class="overflow-x-auto">
                        <table class="w-full min-w-[560px] text-sm">
                          <thead class="text-xs uppercase text-app-muted"><tr><th class="p-2 text-left">Incluir</th><th class="p-2 text-left">Exame / coluna</th><th class="p-2 text-left">Realizado / conteúdo</th><th class="p-2 text-left">Situação</th></tr></thead>
                          <tbody v-if="asoPreview && (historyFor(row).length || ignoredStatesFor(row).length)">
                            <tr v-for="aso in historyFor(row)" :key="aso.key" class="border-t border-app-border">
                              <td class="p-2"><input v-if="aso.status === 'new'" v-model="asoSelected[aso.key]" type="checkbox" :disabled="busy || !canConfirmAsos" /><span v-else class="text-app-muted">—</span></td>
                              <td class="p-2">{{ aso.exam_type || aso.header || 'Não identificado' }} <p class="text-xs text-app-muted">{{ aso.header }}</p></td>
                              <td class="p-2">{{ aso.performed_at || aso.raw }}<p v-if="aso.next_due_at" class="text-xs text-app-muted">Próximo: {{ aso.next_due_at }}</p></td>
                              <td class="p-2"><span :class="aso.status === 'new' ? 'text-app-success' : aso.status === 'review' ? 'text-app-danger' : 'text-app-muted'">{{ {new:'Novo',existing:'Já registrado',review:'Revisar'}[aso.status] }}</span><p v-for="issue in aso.issues" :key="issue" class="text-xs text-app-danger">{{ issue }}</p></td>
                            </tr>
                            <tr v-for="(raw,index) in ignoredStatesFor(row)" :key="`ignored-${index}`" class="border-t border-app-border">
                              <td class="p-2 text-app-muted">—</td><td class="p-2">{{ raw.column }}</td><td class="p-2">{{ raw.value }}</td><td class="p-2 text-app-muted">Estado informativo · não será importado como ASO</td>
                            </tr>
                          </tbody>
                          <tbody v-else>
                            <tr v-for="(aso,index) in row.aso_preview" :key="index" class="border-t border-app-border"><td class="p-2">—</td><td class="p-2">{{ aso.column }}</td><td class="p-2">{{ aso.value }}</td><td class="p-2 text-app-muted">Não conferido</td></tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm text-app-muted"><span>Exibindo {{ shown.length }} de {{ filtered.length }} linhas filtradas.</span><div class="flex items-center gap-3"><button :disabled="page===1" @click="page--">Anterior</button><span>{{ page }} / {{ pages }}</span><button :disabled="page===pages" @click="page++">Próxima</button></div></div>
        <div v-if="!finished && selected.length" class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-app-border pt-4">
          <p class="text-sm font-bold text-app-text">{{ selected.length }} vínculos selecionados para inclusão</p>
          <BaseButton :disabled="busy || !selected.length" @click="commit">{{ busy ? 'Salvando...' : 'Confirmar colaboradores/vínculos' }}</BaseButton>
        </div>
        <div v-if="canConfirmAsos" class="mt-5 rounded-xl border border-app-border bg-app-surface p-4">
          <p class="font-bold text-app-text">Confirmação dos ASOs do mesmo arquivo</p>
          <p class="mt-1 text-sm text-app-muted">{{ selectedAsoKeys.length }} ASOs novos selecionados. Expanda o histórico de cada pessoa para desmarcar ou conferir cada exame.</p>
          <label class="mt-3 flex items-start gap-2 text-sm text-app-text"><input v-model="asoFileConfirmed" type="checkbox" class="mt-1" /> Conferi o arquivo, o vínculo de cada exame e a periodicidade do PCMSO. Confirmo que os ASOs selecionados foram realizados.</label>
          <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
            <p v-if="asoResult" class="text-sm text-app-success">{{ asoResult.result?.created_asos ?? 0 }} ASO(s) importado(s) nesta operação.</p>
            <BaseButton :disabled="busy || !asoPreview || !asoFileConfirmed || !selectedAsoKeys.length" @click="commitAsos">Importar {{ selectedAsoKeys.length }} ASO(s)</BaseButton>
          </div>
        </div>
      </ContentCard>
    <TeamImportRowModal
      :open="editing !== null"
      :row="editingRow"
      :saving="busy"
      :error="correctionError"
      @close="editing = null"
      @submit="saveCorrection"
    />
  </section>
</template>
<style scoped>
.sae-import-input { display:block; width:100%; border:1px solid rgb(var(--color-border)); background:rgb(var(--color-bg)); color:rgb(var(--color-text)); border-radius:.75rem; padding:.7rem .85rem; font-size:.875rem; outline:none; }
.sae-import-input:focus { border-color:rgb(var(--color-primary)); }
.sae-inline-action { color: rgb(var(--color-primary)); font-weight: 700; padding: .4rem .65rem; border: 1px solid rgb(var(--color-border)); border-radius: .6rem; background: rgb(var(--color-surface)); white-space: nowrap; }
.sae-inline-action:hover { border-color: rgb(var(--color-primary)); }
</style>

