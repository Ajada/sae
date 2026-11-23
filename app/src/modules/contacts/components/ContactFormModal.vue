<script setup>
import { computed, reactive, watch } from 'vue'
import { PhX } from '@phosphor-icons/vue'

import BaseButton from '@/shared/components/buttons/BaseButton.vue'
import BaseInput from '@/shared/components/forms/BaseInput.vue'
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
  () => props.contact,
  (contact) => {
    form.name = contact?.name || ''
    form.email = contact?.email || ''
    form.phone = contact?.phone || ''
    form.document = contact?.document || ''
    form.notes = contact?.notes || ''
    form.status = contact?.status || 'active'
  },
  {
    immediate: true,
  },
)

watch(
  () => props.open,
  (open) => {
    if (!open && !props.contact) {
      resetForm()
    }
  },
)

function resetForm() {
  form.name = ''
  form.email = ''
  form.phone = ''
  form.document = ''
  form.notes = ''
  form.status = 'active'
}

function handleClose() {
  if (props.isSaving) return

  emit('close')
}

function handleSubmit() {
  emit('submit', {
    name: form.name,
    email: form.email,
    phone: form.phone,
    document: form.document,
    notes: form.notes,
    status: form.status,
  })
}
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center px-4 py-6">
      <button type="button" class="absolute inset-0 bg-black/60" @click="handleClose" />

      <div
        class="relative z-10 w-full max-w-2xl overflow-hidden rounded-3xl border border-app-border bg-app-bg shadow-2xl"
      >
        <div class="flex items-start justify-between border-b border-app-border px-6 py-5">
          <div>
            <p class="text-xs font-black uppercase tracking-wider text-app-primary">Contatos</p>

            <h2 class="mt-1 text-xl font-black text-app-text">
              {{ title }}
            </h2>

            <p class="mt-1 text-sm text-app-muted">
              Informe os dados básicos do contato para uso em mensagens e automações.
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

        <form class="space-y-5 px-6 py-6" @submit.prevent="handleSubmit">
          <BaseAlert v-if="error" type="danger" :message="error" />

          <div class="grid gap-4 md:grid-cols-2">
            <BaseInput v-model="form.name" label="Nome" placeholder="Ex: Cliente X" required />

            <BaseInput
              v-model="form.email"
              label="E-mail"
              placeholder="cliente@email.com"
              type="email"
            />

            <BaseInput v-model="form.phone" label="Telefone" placeholder="(15) 99999-9999" />

            <BaseInput
              v-model="form.document"
              label="Documento"
              placeholder="CPF/CNPJ, se necessário"
            />
          </div>

          <div>
            <label class="mb-2 block text-sm font-bold text-app-text"> Observações </label>

            <textarea
              v-model="form.notes"
              rows="4"
              class="w-full rounded-2xl border border-app-border bg-app-surface px-4 py-3 text-sm text-app-text outline-none transition placeholder:text-app-muted focus:border-app-primary focus:ring-4 focus:ring-app-primary/10"
              placeholder="Observações internas sobre este contato..."
            />
          </div>

          <div>
            <label class="mb-2 block text-sm font-bold text-app-text"> Status </label>

            <select
              v-model="form.status"
              class="w-full rounded-2xl border border-app-border bg-app-surface px-4 py-3 text-sm font-semibold text-app-text outline-none transition focus:border-app-primary focus:ring-4 focus:ring-app-primary/10"
            >
              <option value="active">Ativo</option>

              <option value="inactive">Inativo</option>
            </select>
          </div>

          <div
            class="flex flex-col-reverse gap-3 border-t border-app-border pt-5 sm:flex-row sm:justify-end"
          >
            <BaseButton type="button" variant="secondary" :disabled="isSaving" @click="handleClose">
              Cancelar
            </BaseButton>

            <BaseButton type="submit" :loading="isSaving">
              {{ submitLabel }}
            </BaseButton>
          </div>
        </form>
      </div>
    </div>
  </Teleport>
</template>
