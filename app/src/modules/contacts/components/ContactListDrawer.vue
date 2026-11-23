<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { PhMagnifyingGlass, PhUsersThree } from '@phosphor-icons/vue'

import BaseDrawer from '@/shared/components/overlay/BaseDrawer.vue'
import BaseButton from '@/shared/components/buttons/BaseButton.vue'
import BaseAlert from '@/shared/components/feedback/BaseAlert.vue'
import LoadingSpinner from '@/shared/components/feedback/LoadingSpinner.vue'

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
  list: {
    type: Object,
    default: null,
  },
  contacts: {
    type: Array,
    default: () => [],
  },
  isSaving: {
    type: Boolean,
    default: false,
  },
  isLoadingContacts: {
    type: Boolean,
    default: false,
  },
  error: {
    type: String,
    default: null,
  },
})

const emit = defineEmits(['close', 'submit'])

const contactSearch = ref('')
const selectedContactIds = ref([])

const form = reactive({
  name: '',
  description: '',
  is_active: true,
})

const isEditing = computed(() => Boolean(props.list))

const title = computed(() => {
  return isEditing.value ? 'Editar lista' : 'Nova lista'
})

const submitLabel = computed(() => {
  return isEditing.value ? 'Salvar alterações' : 'Cadastrar lista'
})

const selectedCount = computed(() => selectedContactIds.value.length)

const filteredContacts = computed(() => {
  const search = contactSearch.value.trim().toLowerCase()

  if (!search) {
    return props.contacts
  }

  return props.contacts.filter((contact) => {
    const name = String(contact.name || '').toLowerCase()
    const email = String(contact.email || '').toLowerCase()
    const phone = String(contact.phone || '').toLowerCase()

    return name.includes(search) || email.includes(search) || phone.includes(search)
  })
})

watch(
  () => props.open,
  (open) => {
    if (open) {
      fillForm(props.list)
    }
  },
)

watch(
  () => props.list,
  (list) => {
    if (props.open) {
      fillForm(list)
    }
  },
)

function fillForm(list) {
  form.name = list?.name || ''
  form.description = list?.description || ''
  form.is_active = list?.is_active ?? true
  contactSearch.value = ''
  selectedContactIds.value = extractSelectedContactIds(list)
}

function extractSelectedContactIds(list) {
  if (!list) return []

  if (Array.isArray(list.contact_ids)) {
    return list.contact_ids
      .map((id) => Number(id))
      .filter(Boolean)
  }

  if (Array.isArray(list.contacts)) {
    return list.contacts
      .map((contact) => Number(contact.id || contact.contact_id))
      .filter(Boolean)
  }

  return []
}

function getContactId(contact) {
  return Number(contact.id || contact.contact_id)
}

function getContactKey(contact) {
  return contact.uuid || contact.id || contact.email
}

function isContactSelected(contact) {
  const contactId = getContactId(contact)

  return selectedContactIds.value.includes(contactId)
}

function toggleContact(contact) {
  const contactId = getContactId(contact)

  if (!contactId) return

  if (selectedContactIds.value.includes(contactId)) {
    selectedContactIds.value = selectedContactIds.value.filter((id) => id !== contactId)
    return
  }

  selectedContactIds.value = [...selectedContactIds.value, contactId]
}

function buildPayload() {
  return {
    name: form.name,
    description: form.description || null,
    is_active: Boolean(form.is_active),
    contact_ids: selectedContactIds.value,
  }
}

function handleSubmit() {
  emit('submit', buildPayload())
}
</script>

<template>
  <BaseDrawer
    :open="open"
    eyebrow="Listas"
    :title="title"
    description="Agrupe contatos para facilitar envios em lote, vencimentos, recorrências e automações."
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
          Dados da lista
        </h3>

        <p class="drawer-help">
          Defina um nome claro para identificar esta lista nos próximos fluxos de mensagem.
        </p>

        <div class="form-grid">
          <label class="form-field form-field-full">
            <span>Nome da lista</span>
            <input
              v-model="form.name"
              type="text"
              placeholder="Ex: Clientes principais"
              required
            >
          </label>

          <label class="form-field form-field-full">
            <span>Descrição</span>
            <textarea
              v-model="form.description"
              rows="4"
              placeholder="Descrição interna para identificar o uso desta lista..."
            />
          </label>
        </div>

        <label class="toggle-card">
          <input
            v-model="form.is_active"
            type="checkbox"
          >

          <div>
            <strong>Lista ativa</strong>
            <span>Permite que esta lista seja usada nos envios e automações.</span>
          </div>
        </label>
      </section>

      <section class="drawer-section">
        <div class="section-row">
          <div>
            <h3 class="drawer-section-title">
              Contatos vinculados
            </h3>

            <p class="drawer-help">
              Selecione os contatos que farão parte desta lista.
            </p>
          </div>

          <span class="selected-badge">
            {{ selectedCount }} selecionados
          </span>
        </div>

        <div class="contact-search">
          <PhMagnifyingGlass class="h-4 w-4" />

          <input
            v-model="contactSearch"
            type="text"
            placeholder="Buscar contato por nome, e-mail ou telefone..."
          >
        </div>

        <div
          v-if="isLoadingContacts"
          class="contact-loading"
        >
          <LoadingSpinner />
        </div>

        <div
          v-else-if="!filteredContacts.length"
          class="empty-contacts"
        >
          <PhUsersThree class="h-8 w-8" />

          <strong>Nenhum contato disponível</strong>

          <span>Cadastre contatos antes de montar uma lista.</span>
        </div>

        <div
          v-else
          class="contact-list"
        >
          <button
            v-for="contact in filteredContacts"
            :key="getContactKey(contact)"
            type="button"
            class="contact-option"
            :class="{ selected: isContactSelected(contact) }"
            @click="toggleContact(contact)"
          >
            <span class="contact-avatar">
              {{ String(contact.name || '?').charAt(0).toUpperCase() }}
            </span>

            <span class="contact-info">
              <strong>{{ contact.name }}</strong>
              <small>{{ contact.email || contact.phone || 'Sem contato informado' }}</small>
            </span>

            <span class="check-box">
              <span v-if="isContactSelected(contact)" />
            </span>
          </button>
        </div>
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

.form-field-full {
  grid-column: 1 / -1;
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
.form-field textarea {
  width: 100%;
  border: 1px solid rgb(var(--color-border));
  border-radius: 1rem;
  background: rgb(var(--color-bg));
  color: rgb(var(--color-text));
  padding: 0.875rem;
  font-size: 0.875rem;
  outline: none;
}

.form-field input {
  height: 2.85rem;
}

.form-field textarea {
  min-height: 7rem;
  resize: vertical;
}

.form-field input:focus,
.form-field textarea:focus,
.contact-search input:focus {
  border-color: rgb(var(--color-primary));
  box-shadow: 0 0 0 4px rgb(var(--color-primary) / 0.12);
}

.toggle-card {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  margin-top: 1rem;
  border: 1px solid rgb(var(--color-border));
  border-radius: 1rem;
  background: rgb(var(--color-bg));
  padding: 0.875rem;
  cursor: pointer;
}

.toggle-card input {
  margin-top: 0.2rem;
  accent-color: rgb(var(--color-primary));
}

.toggle-card strong {
  display: block;
  color: rgb(var(--color-text));
  font-size: 0.875rem;
  font-weight: 900;
}

.toggle-card span {
  display: block;
  margin-top: 0.2rem;
  color: rgb(var(--color-muted));
  font-size: 0.75rem;
  line-height: 1.1rem;
}

.section-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
}

.selected-badge {
  flex-shrink: 0;
  border-radius: 999px;
  background: rgb(var(--color-primary) / 0.1);
  color: rgb(var(--color-primary));
  padding: 0.35rem 0.65rem;
  font-size: 0.72rem;
  font-weight: 900;
}

.contact-search {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-top: 1rem;
  border: 1px solid rgb(var(--color-border));
  border-radius: 1rem;
  background: rgb(var(--color-bg));
  color: rgb(var(--color-muted));
  padding: 0 0.875rem;
}

.contact-search input {
  width: 100%;
  height: 2.85rem;
  border: 0;
  background: transparent;
  color: rgb(var(--color-text));
  font-size: 0.875rem;
  outline: none;
}

.contact-loading,
.empty-contacts {
  display: flex;
  min-height: 12rem;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.6rem;
  margin-top: 1rem;
  border: 1px dashed rgb(var(--color-border));
  border-radius: 1rem;
  color: rgb(var(--color-muted));
  text-align: center;
}

.empty-contacts strong {
  color: rgb(var(--color-text));
  font-size: 0.95rem;
  font-weight: 900;
}

.empty-contacts span {
  max-width: 18rem;
  font-size: 0.8rem;
  line-height: 1.25rem;
}

.contact-list {
  display: grid;
  gap: 0.65rem;
  margin-top: 1rem;
  max-height: 22rem;
  overflow-y: auto;
  padding-right: 0.15rem;
}

.contact-option {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  width: 100%;
  border: 1px solid rgb(var(--color-border));
  border-radius: 1rem;
  background: rgb(var(--color-bg));
  padding: 0.75rem;
  text-align: left;
  transition: border-color 0.2s ease, background 0.2s ease;
}

.contact-option:hover,
.contact-option.selected {
  border-color: rgb(var(--color-primary));
  background: rgb(var(--color-primary) / 0.07);
}

.contact-avatar {
  display: inline-flex;
  height: 2.35rem;
  width: 2.35rem;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  border-radius: 0.85rem;
  background: rgb(var(--color-primary) / 0.12);
  color: rgb(var(--color-primary));
  font-size: 0.85rem;
  font-weight: 900;
}

.contact-info {
  min-width: 0;
  flex: 1;
}

.contact-info strong,
.contact-info small {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.contact-info strong {
  color: rgb(var(--color-text));
  font-size: 0.88rem;
  font-weight: 900;
}

.contact-info small {
  margin-top: 0.15rem;
  color: rgb(var(--color-muted));
  font-size: 0.75rem;
}

.check-box {
  display: inline-flex;
  height: 1.15rem;
  width: 1.15rem;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  border: 1px solid rgb(var(--color-border));
  border-radius: 0.4rem;
  background: rgb(var(--color-surface));
}

.contact-option.selected .check-box {
  border-color: rgb(var(--color-primary));
  background: rgb(var(--color-primary));
}

.check-box span {
  height: 0.45rem;
  width: 0.45rem;
  border-radius: 999px;
  background: #ffffff;
}
</style>