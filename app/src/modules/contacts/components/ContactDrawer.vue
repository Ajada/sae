<script setup>
import { computed, reactive, watch } from 'vue'

import BaseDrawer from '@/shared/components/overlay/BaseDrawer.vue'
import BaseButton from '@/shared/components/buttons/BaseButton.vue'
import BaseAlert from '@/shared/components/feedback/BaseAlert.vue'

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
  contact: {
    type: Object,
    default: null,
  },
  isSaving: {
    type: Boolean,
    default: false,
  },
  error: {
    type: String,
    default: null,
  },
})

const emit = defineEmits(['close', 'submit'])

const form = reactive({
  name: '',
  email: '',
  phone: '',
  document: '',
  notes: '',
  status: 'active',
})

const isEditing = computed(() => Boolean(props.contact))

const title = computed(() => {
  return isEditing.value ? 'Editar contato' : 'Novo contato'
})

const submitLabel = computed(() => {
  return isEditing.value ? 'Salvar alterações' : 'Cadastrar contato'
})

watch(
  () => props.open,
  (open) => {
    if (open) {
      fillForm(props.contact)
    }
  },
)

watch(
  () => props.contact,
  (contact) => {
    if (props.open) {
      fillForm(contact)
    }
  },
)

function fillForm(contact) {
  form.name = contact?.name || ''
  form.email = contact?.email || ''
  form.phone = contact?.phone || ''
  form.document = contact?.document || ''
  form.notes = contact?.notes || ''
  form.status = contact?.status || 'active'
}

function buildPayload() {
  return {
    name: form.name,
    email: form.email || null,
    phone: form.phone || null,
    document: form.document || null,
    notes: form.notes || null,
    status: form.status,
  }
}

function handleSubmit() {
  emit('submit', buildPayload())
}
</script>

<template>
  <BaseDrawer
    :open="open"
    eyebrow="Contatos"
    :title="title"
    description="Cadastre contatos individuais para uso em mensagens, vencimentos, listas e automações."
    @close="$emit('close')"
  >
    <div class="space-y-6">
      <BaseAlert
        v-if="error"
        type="danger"
        :message="error"
      />

      <section class="drawer-section">
        <h3 class="drawer-section-title">
          Dados principais
        </h3>

        <p class="drawer-help">
          Informe os dados básicos do contato. Nome é obrigatório; os demais campos podem ser usados nos envios futuros.
        </p>

        <div class="form-grid">
          <label class="form-field">
            <span>Nome</span>
            <input
              v-model="form.name"
              type="text"
              placeholder="Ex: Cliente X"
              required
            >
          </label>

          <label class="form-field">
            <span>E-mail</span>
            <input
              v-model="form.email"
              type="email"
              placeholder="cliente@email.com"
            >
          </label>

          <label class="form-field">
            <span>Telefone</span>
            <input
              v-model="form.phone"
              type="text"
              placeholder="(15) 99999-9999"
            >
          </label>

          <label class="form-field">
            <span>Documento</span>
            <input
              v-model="form.document"
              type="text"
              placeholder="CPF/CNPJ, se necessário"
            >
          </label>
        </div>
      </section>

      <section class="drawer-section">
        <h3 class="drawer-section-title">
          Observações
        </h3>

        <p class="drawer-help">
          Use este campo para anotações internas sobre o contato.
        </p>

        <label class="form-field mt-4">
          <span>Observações internas</span>
          <textarea
            v-model="form.notes"
            rows="5"
            placeholder="Observações internas sobre este contato..."
          />
        </label>
      </section>

      <section class="drawer-section">
        <h3 class="drawer-section-title">
          Status
        </h3>

        <label class="form-field mt-4">
          <span>Status do contato</span>
          <select v-model="form.status">
            <option value="active">
              Ativo
            </option>

            <option value="inactive">
              Inativo
            </option>
          </select>
        </label>
      </section>
    </div>

    <template #footer>
      <BaseButton
        type="button"
        variant="secondary"
        :disabled="isSaving"
        @click="$emit('close')"
      >
        Cancelar
      </BaseButton>

      <BaseButton
        type="button"
        :loading="isSaving"
        @click="handleSubmit"
      >
        {{ submitLabel }}
      </BaseButton>
    </template>
  </BaseDrawer>
</template>

<style scoped>
.drawer-section {
  border: 1px solid rgb(var(--color-border));
  border-radius: 1.25rem;
  background: rgb(var(--color-surface));
  padding: 1rem;
}

.drawer-section-title {
  color: rgb(var(--color-text));
  font-size: 0.95rem;
  line-height: 1.25rem;
  font-weight: 900;
  margin-bottom: 0.35rem;
}

.drawer-help {
  color: rgb(var(--color-muted));
  font-size: 0.82rem;
  line-height: 1.35rem;
}

.form-grid {
  display: grid;
  gap: 1rem;
  margin-top: 1rem;
}

@media (min-width: 640px) {
  .form-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.form-field span {
  color: rgb(var(--color-text));
  font-size: 0.875rem;
  line-height: 1.25rem;
  font-weight: 800;
}

.form-field input,
.form-field textarea,
.form-field select {
  width: 100%;
  border: 1px solid rgb(var(--color-border));
  border-radius: 1rem;
  background: rgb(var(--color-bg));
  color: rgb(var(--color-text));
  padding: 0.875rem;
  font-size: 0.875rem;
  outline: none;
}

.form-field input,
.form-field select {
  height: 2.85rem;
}

.form-field textarea {
  min-height: 8rem;
  resize: vertical;
}

.form-field input:focus,
.form-field textarea:focus,
.form-field select:focus {
  border-color: rgb(var(--color-primary));
  box-shadow: 0 0 0 4px rgb(var(--color-primary) / 0.12);
}
</style>