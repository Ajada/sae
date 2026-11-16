<script setup>
import { computed, reactive, watch } from 'vue'
import { PhX } from '@phosphor-icons/vue'

import BaseButton from '@/shared/components/buttons/BaseButton.vue'
import BaseInput from '@/shared/components/forms/BaseInput.vue'
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

const form = reactive({
  name: '',
  description: '',
  is_active: true,
  contact_ids: [],
})

const isEditing = computed(() => Boolean(props.list))

const title = computed(() => {
  return isEditing.value ? 'Editar lista' : 'Nova lista'
})

const submitLabel = computed(() => {
  return isEditing.value ? 'Salvar alterações' : 'Cadastrar lista'
})

watch(
  () => props.list,
  (list) => {
    form.name = list?.name || ''
    form.description = list?.description || ''
    form.is_active = list?.is_active ?? true
    form.contact_ids = extractContactIds(list)
  },
  {
    immediate: true,
  },
)

watch(
  () => props.open,
  (open) => {
    if (open && !props.list) {
      resetForm()
    }
  },
)

function extractContactIds(list) {
  if (!list?.contacts || !Array.isArray(list.contacts)) {
    return []
  }

  return list.contacts
    .map((contact) => contact.id)
    .filter(Boolean)
}

function resetForm() {
  form.name = ''
  form.description = ''
  form.is_active = true
  form.contact_ids = []
}

function handleClose() {
  if (props.isSaving) return

  emit('close')
}

function toggleContact(contactId) {
  if (form.contact_ids.includes(contactId)) {
    form.contact_ids = form.contact_ids.filter((id) => id !== contactId)
    return
  }

  form.contact_ids.push(contactId)
}

function isSelected(contactId) {
  return form.contact_ids.includes(contactId)
}

function handleSubmit() {
  emit('submit', {
    name: form.name,
    description: form.description,
    is_active: Boolean(form.is_active),
    contact_ids: form.contact_ids,
  })
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-50 flex items-center justify-center px-4 py-6"
    >
      <button
        type="button"
        class="absolute inset-0 bg-black/60"
        @click="handleClose"
      />

      <div class="relative z-10 flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-3xl border border-app-border bg-app-bg shadow-2xl">
        <div class="flex items-start justify-between border-b border-app-border px-6 py-5">
          <div>
            <p class="text-xs font-black uppercase tracking-wider text-app-primary">
              Listas
            </p>

            <h2 class="mt-1 text-xl font-black text-app-text">
              {{ title }}
            </h2>

            <p class="mt-1 text-sm text-app-muted">
              Agrupe contatos para facilitar envios, automações e vencimentos.
            </p>
          </div>

          <button
            type="button"
            class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-app-border bg-app-surface text-app-muted transition hover:text-app-text"
            @click="handleClose"
          >
            <PhX class="h-5 w-5" />
          </button>
        </div>

        <form
          class="flex-1 overflow-y-auto px-6 py-6"
          @submit.prevent="handleSubmit"
        >
          <BaseAlert
            v-if="error"
            type="danger"
            :message="error"
          />

          <div class="mt-5 grid gap-4 md:grid-cols-2">
            <BaseInput
              v-model="form.name"
              label="Nome da lista"
              placeholder="Ex: Clientes principais"
              required
            />

            <div>
              <label class="mb-2 block text-sm font-bold text-app-text">
                Status
              </label>

              <select
                v-model="form.is_active"
                class="h-11 w-full rounded-2xl border border-app-border bg-app-surface px-4 text-sm font-semibold text-app-text outline-none transition focus:border-app-primary focus:ring-4 focus:ring-app-primary/10"
              >
                <option :value="true">
                  Ativa
                </option>

                <option :value="false">
                  Inativa
                </option>
              </select>
            </div>
          </div>

          <div class="mt-5">
            <label class="mb-2 block text-sm font-bold text-app-text">
              Descrição
            </label>

            <textarea
              v-model="form.description"
              rows="3"
              class="w-full rounded-2xl border border-app-border bg-app-surface px-4 py-3 text-sm text-app-text outline-none transition placeholder:text-app-muted focus:border-app-primary focus:ring-4 focus:ring-app-primary/10"
              placeholder="Descrição interna para identificar o uso desta lista..."
            />
          </div>

          <div class="mt-6">
            <div class="mb-3 flex items-center justify-between gap-3">
              <div>
                <h3 class="text-sm font-black text-app-text">
                  Contatos vinculados
                </h3>

                <p class="mt-1 text-xs text-app-muted">
                  Selecione os contatos que farão parte desta lista.
                </p>
              </div>

              <span class="rounded-full border border-app-border px-3 py-1 text-xs font-black text-app-muted">
                {{ form.contact_ids.length }} selecionados
              </span>
            </div>

            <div
              v-if="isLoadingContacts"
              class="flex min-h-40 items-center justify-center rounded-2xl border border-app-border bg-app-surface"
            >
              <LoadingSpinner />
            </div>

            <div
              v-else-if="contacts.length === 0"
              class="rounded-2xl border border-app-border bg-app-surface p-5 text-sm text-app-muted"
            >
              Nenhum contato disponível. Cadastre contatos antes de criar uma lista.
            </div>

            <div
              v-else
              class="max-h-72 overflow-y-auto rounded-2xl border border-app-border"
            >
              <button
                v-for="contact in contacts"
                :key="contact.id"
                type="button"
                class="flex w-full items-center justify-between gap-4 border-b border-app-border px-4 py-3 text-left transition last:border-b-0 hover:bg-app-surface"
                @click="toggleContact(contact.id)"
              >
                <div class="min-w-0">
                  <p class="truncate text-sm font-bold text-app-text">
                    {{ contact.name }}
                  </p>

                  <p class="truncate text-xs text-app-muted">
                    {{ contact.email || contact.phone || 'Sem e-mail/telefone' }}
                  </p>
                </div>

                <span
                  class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md border"
                  :class="isSelected(contact.id)
                    ? 'border-app-primary bg-app-primary'
                    : 'border-app-border bg-app-bg'"
                >
                  <span
                    v-if="isSelected(contact.id)"
                    class="h-2 w-2 rounded-full bg-white"
                  />
                </span>
              </button>
            </div>
          </div>
        </form>

        <div class="flex flex-col-reverse gap-3 border-t border-app-border px-6 py-5 sm:flex-row sm:justify-end">
          <BaseButton
            type="button"
            variant="secondary"
            :disabled="isSaving"
            @click="handleClose"
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
        </div>
      </div>
    </div>
  </Teleport>
</template>