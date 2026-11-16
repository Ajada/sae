<script setup>
import { computed, onMounted, ref } from 'vue'
import { PhMagnifyingGlass, PhPencilSimple, PhPlus, PhTrash, PhUsers } from '@phosphor-icons/vue'

import PageHeader from '@/shared/components/layout/PageHeader.vue'
import ContentCard from '@/shared/components/cards/ContentCard.vue'
import BaseButton from '@/shared/components/buttons/BaseButton.vue'
import BaseInput from '@/shared/components/forms/BaseInput.vue'
import BaseAlert from '@/shared/components/feedback/BaseAlert.vue'
import LoadingSpinner from '@/shared/components/feedback/LoadingSpinner.vue'

import ContactDrawer from '../components/ContactDrawer.vue'
import { useContactStore } from '../stores/contact.store'

const contactStore = useContactStore()

const isModalOpen = ref(false)
const editingContact = ref(null)
const search = ref('')

const contacts = computed(() => contactStore.items)
const pagination = computed(() => contactStore.pagination)

const hasContacts = computed(() => contacts.value.length > 0)

onMounted(() => {
  loadContacts()
})

async function loadContacts(params = {}) {
  await contactStore.fetchItems({
    ...params,
  })
}

async function handleSearch() {
  contactStore.setFilters({
    search: search.value,
    page: 1,
  })

  await loadContacts()
}

function openCreateModal() {
  editingContact.value = null
  contactStore.error = null
  isModalOpen.value = true
}

function openEditModal(contact) {
  editingContact.value = contact
  contactStore.error = null
  isModalOpen.value = true
}

function closeModal() {
  isModalOpen.value = false
  editingContact.value = null
}

function getContactKey(contact) {
  return contact.uuid || contact.id
}

async function handleSubmit(payload) {
  if (editingContact.value) {
    await contactStore.updateItem(getContactKey(editingContact.value), payload)
  } else {
    await contactStore.createItem(payload)
  }

  closeModal()

  await loadContacts()
}

async function handleDelete(contact) {
  const confirmed = window.confirm(`Deseja realmente remover o contato "${contact.name}"?`)

  if (!confirmed) return

  await contactStore.removeItem(getContactKey(contact))

  if (contacts.value.length === 0 && pagination.value.currentPage > 1) {
    await goToPage(pagination.value.currentPage - 1)
  }
}

async function goToPage(page) {
  if (page < 1 || page > pagination.value.lastPage) return

  contactStore.setFilters({
    page,
  })

  await loadContacts()
}

function formatEmpty(value) {
  return value || '-'
}

function statusLabel(status) {
  const labels = {
    active: 'Ativo',
    inactive: 'Inativo',
  }

  return labels[status] || status || '-'
}

function statusClass(status) {
  return status === 'active'
    ? 'bg-app-success/10 text-app-success'
    : 'bg-app-muted/10 text-app-muted'
}
</script>

<template>
  <section>
    <PageHeader
      eyebrow="Cadastros"
      title="Contatos"
      description="Gerencie contatos individuais que poderão receber e-mails, avisos e mensagens futuras."
    >
      <template #actions>
        <BaseButton @click="openCreateModal">
          <PhPlus class="h-4 w-4" />
          Novo contato
        </BaseButton>
      </template>
    </PageHeader>

    <ContentCard>
      <div
        class="flex flex-col gap-4 border-b border-app-border pb-5 lg:flex-row lg:items-end lg:justify-between"
      >
        <div>
          <h2 class="text-lg font-black text-app-text">Lista de contatos</h2>

          <p class="mt-1 text-sm text-app-muted">
            Pesquise, cadastre, edite e remova contatos da empresa ativa.
          </p>
        </div>

        <form
          class="flex w-full flex-col gap-3 sm:flex-row lg:max-w-xl"
          @submit.prevent="handleSearch"
        >
          <BaseInput
            v-model="search"
            placeholder="Buscar por nome, e-mail ou telefone..."
            class="flex-1"
          />

          <BaseButton type="submit" variant="secondary" :disabled="contactStore.isLoading">
            <PhMagnifyingGlass class="h-4 w-4" />
            Buscar
          </BaseButton>
        </form>
      </div>

      <BaseAlert
        v-if="contactStore.error"
        class="mt-5"
        type="danger"
        :message="contactStore.error"
      />

      <div v-if="contactStore.isLoading" class="flex min-h-80 items-center justify-center">
        <LoadingSpinner />
      </div>

      <div
        v-else-if="!hasContacts"
        class="flex min-h-80 flex-col items-center justify-center text-center"
      >
        <div
          class="flex h-16 w-16 items-center justify-center rounded-3xl bg-app-primary/10 text-app-primary"
        >
          <PhUsers class="h-8 w-8" />
        </div>

        <h3 class="mt-4 text-lg font-black text-app-text">Nenhum contato encontrado</h3>

        <p class="mt-2 max-w-md text-sm text-app-muted">
          Cadastre seu primeiro contato para começar a enviar mensagens e criar automações.
        </p>

        <BaseButton class="mt-5" @click="openCreateModal">
          <PhPlus class="h-4 w-4" />
          Cadastrar contato
        </BaseButton>
      </div>

      <div v-else class="mt-5 overflow-hidden rounded-2xl border border-app-border">
        <div class="hidden overflow-x-auto lg:block">
          <table class="min-w-full divide-y divide-app-border">
            <thead class="bg-app-surface">
              <tr>
                <th
                  class="px-5 py-4 text-left text-xs font-black uppercase tracking-wider text-app-muted"
                >
                  Nome
                </th>

                <th
                  class="px-5 py-4 text-left text-xs font-black uppercase tracking-wider text-app-muted"
                >
                  E-mail
                </th>

                <th
                  class="px-5 py-4 text-left text-xs font-black uppercase tracking-wider text-app-muted"
                >
                  Telefone
                </th>

                <th
                  class="px-5 py-4 text-left text-xs font-black uppercase tracking-wider text-app-muted"
                >
                  Status
                </th>

                <th
                  class="px-5 py-4 text-right text-xs font-black uppercase tracking-wider text-app-muted"
                >
                  Ações
                </th>
              </tr>
            </thead>

            <tbody class="divide-y divide-app-border bg-app-bg">
              <tr
                v-for="contact in contacts"
                :key="getContactKey(contact)"
                class="transition hover:bg-app-surface/70"
              >
                <td class="px-5 py-4">
                  <p class="font-bold text-app-text">
                    {{ contact.name }}
                  </p>

                  <p v-if="contact.document" class="mt-1 text-xs text-app-muted">
                    {{ contact.document }}
                  </p>
                </td>

                <td class="px-5 py-4 text-sm text-app-muted">
                  {{ formatEmpty(contact.email) }}
                </td>

                <td class="px-5 py-4 text-sm text-app-muted">
                  {{ formatEmpty(contact.phone) }}
                </td>

                <td class="px-5 py-4">
                  <span
                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-black"
                    :class="statusClass(contact.status)"
                  >
                    {{ statusLabel(contact.status) }}
                  </span>
                </td>

                <td class="px-5 py-4">
                  <div class="flex justify-end gap-2">
                    <button
                      type="button"
                      class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-app-border bg-app-surface text-app-muted transition hover:text-app-primary"
                      title="Editar"
                      @click="openEditModal(contact)"
                    >
                      <PhPencilSimple class="h-4 w-4" />
                    </button>

                    <button
                      type="button"
                      class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-app-border bg-app-surface text-app-muted transition hover:text-app-danger"
                      title="Remover"
                      @click="handleDelete(contact)"
                    >
                      <PhTrash class="h-4 w-4" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="divide-y divide-app-border lg:hidden">
          <article v-for="contact in contacts" :key="getContactKey(contact)" class="p-4">
            <div class="flex items-start justify-between gap-3">
              <div>
                <h3 class="font-black text-app-text">
                  {{ contact.name }}
                </h3>

                <p class="mt-1 text-sm text-app-muted">
                  {{ formatEmpty(contact.email) }}
                </p>

                <p class="mt-1 text-sm text-app-muted">
                  {{ formatEmpty(contact.phone) }}
                </p>
              </div>

              <span
                class="inline-flex shrink-0 rounded-full px-2.5 py-1 text-xs font-black"
                :class="statusClass(contact.status)"
              >
                {{ statusLabel(contact.status) }}
              </span>
            </div>

            <div class="mt-4 flex gap-2">
              <BaseButton size="sm" variant="secondary" @click="openEditModal(contact)">
                Editar
              </BaseButton>

              <BaseButton size="sm" variant="danger" @click="handleDelete(contact)">
                Remover
              </BaseButton>
            </div>
          </article>
        </div>
      </div>

      <div
        v-if="hasContacts"
        class="mt-5 flex flex-col gap-3 border-t border-app-border pt-5 sm:flex-row sm:items-center sm:justify-between"
      >
        <p class="text-sm text-app-muted">
          Mostrando
          <strong class="text-app-text">{{ pagination.from }}</strong>
          até
          <strong class="text-app-text">{{ pagination.to }}</strong>
          de
          <strong class="text-app-text">{{ pagination.total }}</strong>
          registros.
        </p>

        <div class="flex items-center gap-2">
          <BaseButton
            size="sm"
            variant="secondary"
            :disabled="pagination.currentPage <= 1 || contactStore.isLoading"
            @click="goToPage(pagination.currentPage - 1)"
          >
            Anterior
          </BaseButton>

          <span
            class="rounded-xl border border-app-border bg-app-surface px-3 py-2 text-sm font-bold text-app-muted"
          >
            {{ pagination.currentPage }} / {{ pagination.lastPage }}
          </span>

          <BaseButton
            size="sm"
            variant="secondary"
            :disabled="pagination.currentPage >= pagination.lastPage || contactStore.isLoading"
            @click="goToPage(pagination.currentPage + 1)"
          >
            Próxima
          </BaseButton>
        </div>
      </div>
    </ContentCard>

    <ContactDrawer
      :open="isModalOpen"
      :contact="editingContact"
      :is-saving="contactStore.isSaving"
      :error="contactStore.error"
      @close="closeModal"
      @submit="handleSubmit"
    />
  </section>
</template>
