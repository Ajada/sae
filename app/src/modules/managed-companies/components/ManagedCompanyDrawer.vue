<script setup>
import { computed, reactive, watch } from 'vue'
import BaseDrawer from '@/shared/components/overlay/BaseDrawer.vue'
import BaseButton from '@/shared/components/buttons/BaseButton.vue'
import BaseAlert from '@/shared/components/feedback/BaseAlert.vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  company: { type: Object, default: null },
  isSaving: { type: Boolean, default: false },
  error: { type: String, default: null },
})
const emit = defineEmits(['close', 'submit'])
const form = reactive({
  corporate_name: '', trade_name: '', cnpj: '', risk_grade: '2',
  email: '', phone: '', is_active: true, notes: '',
})
const isEditing = computed(() => Boolean(props.company))
const onlyDigits = (value) => String(value ?? '').replace(/\D/g, '')
const canSubmit = computed(() => form.corporate_name.trim() !== '' && onlyDigits(form.cnpj).length === 14 && !props.isSaving)

function maskCnpj(raw) {
  const digits = onlyDigits(raw).slice(0, 14)
  return digits.replace(/^(\d{2})(\d)/, '$1.$2')
    .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
    .replace(/\.(\d{3})(\d)/, '.$1/$2')
    .replace(/(\d{4})(\d)/, '$1-$2')
}
function fillForm(company) {
  form.corporate_name = company?.corporate_name ?? ''
  form.trade_name = company?.trade_name ?? ''
  form.cnpj = maskCnpj(company?.cnpj ?? '')
  form.risk_grade = String(company?.risk_grade ?? '2')
  form.email = company?.email ?? ''
  form.phone = company?.phone ?? ''
  form.is_active = company?.is_active ?? true
  form.notes = company?.notes ?? ''
}
watch(() => [props.open, props.company], () => { if (props.open) fillForm(props.company) }, { immediate: true })
function submit() {
  if (!canSubmit.value) return
  emit('submit', {
    corporate_name: form.corporate_name.trim(),
    trade_name: form.trade_name.trim() || null,
    cnpj: onlyDigits(form.cnpj),
    risk_grade: Number(form.risk_grade),
    email: form.email.trim() || null,
    phone: form.phone.trim() || null,
    is_active: Boolean(form.is_active),
    notes: form.notes.trim() || null,
  })
}
</script>

<template>
  <BaseDrawer
    :open="open"
    eyebrow="Gestão de empresas"
    :title="isEditing ? 'Editar empresa' : 'Nova empresa'"
    description="Cadastre a empresa cliente que será acompanhada pelo SAE."
    @close="emit('close')"
  >
    <form id="managed-company-form" class="space-y-5" @submit.prevent="submit">
      <BaseAlert v-if="error" type="danger" :message="error" />
      <section class="drawer-section">
        <h3 class="drawer-section-title">Identificação</h3>
        <p class="drawer-help">A empresa cadastrada ficará vinculada à conta do assinante ativa.</p>
        <div class="form-grid">
          <label class="form-field wide"><span>Razão social *</span>
            <input v-model="form.corporate_name" required maxlength="255" placeholder="Razão social da empresa" />
          </label>
          <label class="form-field"><span>Nome fantasia</span>
            <input v-model="form.trade_name" maxlength="255" placeholder="Nome fantasia" />
          </label>
          <label class="form-field"><span>CNPJ *</span>
            <input :value="form.cnpj" inputmode="numeric" maxlength="18" required placeholder="00.000.000/0000-00" @input="form.cnpj = maskCnpj($event.target.value)" />
          </label>
          <label class="form-field"><span>Grau de risco *</span>
            <select v-model="form.risk_grade" required>
              <option value="1">Grau 1</option><option value="2">Grau 2</option>
              <option value="3">Grau 3</option><option value="4">Grau 4</option>
            </select>
          </label>
          <label class="form-field"><span>Situação</span>
            <select v-model="form.is_active">
              <option :value="true">Ativa</option><option :value="false">Inativa</option>
            </select>
          </label>
        </div>
      </section>
      <section class="drawer-section">
        <h3 class="drawer-section-title">Contato e observações</h3>
        <div class="form-grid">
          <label class="form-field"><span>E-mail</span><input v-model="form.email" type="email" maxlength="255" placeholder="empresa@exemplo.com" /></label>
          <label class="form-field"><span>Telefone</span><input v-model="form.phone" maxlength="30" placeholder="(15) 99999-9999" /></label>
          <label class="form-field wide"><span>Observações internas</span><textarea v-model="form.notes" rows="4" placeholder="Anotações da equipe..." /></label>
        </div>
      </section>
    </form>
    <template #footer>
      <BaseButton type="button" variant="secondary" :disabled="isSaving" @click="emit('close')">Cancelar</BaseButton>
      <BaseButton type="button" :loading="isSaving" :disabled="!canSubmit" @click="submit">
        {{ isEditing ? 'Salvar alterações' : 'Cadastrar empresa' }}
      </BaseButton>
    </template>
  </BaseDrawer>
</template>

<style scoped>
.drawer-section { border:1px solid rgb(var(--color-border)); border-radius:1.25rem; background:rgb(var(--color-surface)); padding:1rem; }
.drawer-section-title { color:rgb(var(--color-text)); font-size:.95rem; font-weight:900; margin-bottom:.35rem; }
.drawer-help { color:rgb(var(--color-muted)); font-size:.82rem; line-height:1.35; }
.form-grid { display:grid; gap:1rem; margin-top:1rem; }
@media(min-width:640px){ .form-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } .wide { grid-column:1/-1; } }
.form-field { display:flex; flex-direction:column; gap:.5rem; }
.form-field span { color:rgb(var(--color-text)); font-size:.875rem; font-weight:800; }
.form-field input,.form-field select,.form-field textarea { width:100%; border:1px solid rgb(var(--color-border)); border-radius:1rem; background:rgb(var(--color-bg)); color:rgb(var(--color-text)); padding:.875rem; font-size:.875rem; outline:none; }
.form-field input,.form-field select { height:2.85rem; }
.form-field textarea { resize:vertical; }
.form-field input:focus,.form-field select:focus,.form-field textarea:focus { border-color:rgb(var(--color-primary)); box-shadow:0 0 0 4px rgb(var(--color-primary)/.12); }
</style>
