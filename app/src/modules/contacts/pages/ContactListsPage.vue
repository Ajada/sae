<script setup>
import { computed, onMounted, ref } from 'vue'
import {
  PhListChecks,
  PhMagnifyingGlass,
  PhPencilSimple,
  PhPlus,
  PhTrash,
} from '@phosphor-icons/vue'

import PageHeader from '@/shared/components/layout/PageHeader.vue'
import ContentCard from '@/shared/components/cards/ContentCard.vue'
import BaseButton from '@/shared/components/buttons/BaseButton.vue'
import BaseInput from '@/shared/components/forms/BaseInput.vue'
import BaseAlert from '@/shared/components/feedback/BaseAlert.vue'
import LoadingSpinner from '@/shared/components/feedback/LoadingSpinner.vue'

import ContactListDrawer from '../components/ContactListDrawer.vue'
import { useContactListStore } from '../stores/contactList.store'
import { useContactStore } from '../stores/contact.store'

const contactListStore = useContactListStore()
const contactStore = useContactStore()

const isModalOpen = ref(false)
const editingList = ref(null)
const search = ref('')

const lists = computed(() => contactListStore.items)
const contacts = computed(() => contactStore.items)
const pagination = computed(() => contactListStore.pagination)

const hasLists = computed(() => lists.value.length > 0)

onMounted(async () => {
  await loadLists()
})

async function loadLists(params = {}) {
  await contactListStore.fetchItems({
    ...params,
  })
}

async function loadContactsForSelection() {
  await contactStore.fetchItems({
    page: 1,
    per_page: 200,
    status: '',
    search: '',
  })
}

async function handleSearch() {
  contactListStore.setFilters({
    search: search.value,
    page: 1,
  })

  await loadLists()
}

async function openCreateModal() {
  editingList.value = null
  contactListStore.error = null

  await loadContactsForSelection()

  isModalOpen.value = true
}

async function openEditModal(list) {
  editingList.value = list
  contactListStore.error = null

  await loadContactsForSelection()

  isModalOpen.value = true
}

function closeModal() {
  isModalOpen.value = false
  editingList.value = null
}

function getModelKey(model) {
  return model?.uuid || model?.id
}

async function handleSubmit(payload) {
  if (editingList.value) {
    await contactListStore.updateItem(getModelKey(editingList.value), payload)
  } else {
    await contactListStore.createItem(payload)
  }

  closeModal()

  await loadLists()
}

async function handleDelete(list) {
  const confirmed = window.confirm(`Deseja realmente remover a lista "${list.name}"?`)

  if (!confirmed) return

  await contactListStore.removeItem(getModelKey(list))

  if (lists.value.length === 0 && pagination.value.currentPage > 1) {
    await goToPage(pagination.value.currentPage - 1)
  }
}

async function goToPage(page) {
  if (page < 1 || page > pagination.value.lastPage) return

  contactListStore.setFilters({
    page,
  })

  await loadLists()
}

function statusLabel(isActive) {
  return isActive ? 'Ativa' : 'Inativa'
}

function statusClass(isActive) {
  return isActive ? 'bg-app-success/10 text-app-success' : 'bg-app-muted/10 text-app-muted'
}

function contactsCount(list) {
  if (typeof list.contacts_count === 'number') {
    return list.contacts_count
  }

  if (Array.isArray(list.contacts)) {
    return list.contacts.length
  }

  return 0
}
</script>

<template>
  <section>
    <PageHeader
      eyebrow="Cadastros"
      title="Listas de contatos"
      description="Organize contatos em grupos para envios em lote, vencimentos e recorrências."
    >
      <template #actions>
        <BaseButton @click="openCreateModal">
          <PhPlus class="h-4 w-4" />
          Nova lista
        </BaseButton>
      </template>
    </PageHeader>

    <ContentCard>
      <div
        class="flex flex-col gap-4 border-b border-app-border pb-5 lg:flex-row lg:items-end lg:justify-between"
      >
        <div>
          <h2 class="text-lg font-black text-app-text">Listas cadastradas</h2>

          <p class="mt-1 text-sm text-app-muted">
            Crie grupos de contatos para facilitar os próximos fluxos de mensagem.
          </p>
        </div>

        <form
          class="flex w-full flex-col gap-3 sm:flex-row lg:max-w-xl"
          @submit.prevent="handleSearch"
        >
          <BaseInput v-model="search" placeholder="Buscar lista por nome..." class="flex-1" />

          <BaseButton type="submit" variant="secondary" :disabled="contactListStore.isLoading">
            <PhMagnifyingGlass class="h-4 w-4" />
            Buscar
          </BaseButton>
        </form>
      </div>

      <BaseAlert
        v-if="contactListStore.error"
        class="mt-5"
        type="danger"
        :message="contactListStore.error"
      />

      <div v-if="contactListStore.isLoading" class="flex min-h-80 items-center justify-center">
        <LoadingSpinner />
      </div>

      <div
        v-else-if="!hasLists"
        class="flex min-h-80 flex-col items-center justify-center text-center"
      >
        <div
          class="flex h-16 w-16 items-center justify-center rounded-3xl bg-app-primary/10 text-app-primary"
        >
          <PhListChecks class="h-8 w-8" />
        </div>

        <h3 class="mt-4 text-lg font-black text-app-text">Nenhuma lista encontrada</h3>

        <p class="mt-2 max-w-md text-sm text-app-muted">
          Crie sua primeira lista para organizar contatos e facilitar envios em lote.
        </p>

        <BaseButton class="mt-5" @click="openCreateModal">
          <PhPlus class="h-4 w-4" />
          Cadastrar lista
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
                  Descrição
                </th>

                <th
                  class="px-5 py-4 text-left text-xs font-black uppercase tracking-wider text-app-muted"
                >
                  Contatos
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
                v-for="list in lists"
                :key="getModelKey(list)"
                class="transition hover:bg-app-surface/70"
              >
                <td class="px-5 py-4">
                  <p class="font-bold text-app-text">
                    {{ list.name }}
                  </p>
                </td>

                <td class="px-5 py-4 text-sm text-app-muted">
                  {{ list.description || '-' }}
                </td>

                <td class="px-5 py-4 text-sm font-bold text-app-text">
                  {{ contactsCount(list) }}
                </td>

                <td class="px-5 py-4">
                  <span
                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-black"
                    :class="statusClass(list.is_active)"
                  >
                    {{ statusLabel(list.is_active) }}
                  </span>
                </td>

                <td class="px-5 py-4">
                  <div class="flex justify-end gap-2">
                    <button
                      type="button"
                      class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-app-border bg-app-surface text-app-muted transition hover:text-app-primary"
                      title="Editar"
                      @click="openEditModal(list)"
                    >
                      <PhPencilSimple class="h-4 w-4" />
                    </button>

                    <button
                      type="button"
                      class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-app-border bg-app-surface text-app-muted transition hover:text-app-danger"
                      title="Remover"
                      @click="handleDelete(list)"
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
          <article v-for="list in lists" :key="getModelKey(list)" class="p-4">
            <div class="flex items-start justify-between gap-3">
              <div>
                <h3 class="font-black text-app-text">
                  {{ list.name }}
                </h3>

                <p class="mt-1 text-sm text-app-muted">
                  {{ list.description || 'Sem descrição' }}
                </p>

                <p class="mt-2 text-xs font-bold text-app-muted">
                  {{ contactsCount(list) }} contatos
                </p>
              </div>

              <span
                class="inline-flex shrink-0 rounded-full px-2.5 py-1 text-xs font-black"
                :class="statusClass(list.is_active)"
              >
                {{ statusLabel(list.is_active) }}
              </span>
            </div>

            <div class="mt-4 flex gap-2">
              <BaseButton size="sm" variant="secondary" @click="openEditModal(list)">
                Editar
              </BaseButton>

              <BaseButton size="sm" variant="danger" @click="handleDelete(list)">
                Remover
              </BaseButton>
            </div>
          </article>
        </div>
      </div>

      <div
        v-if="hasLists"
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
            :disabled="pagination.currentPage <= 1 || contactListStore.isLoading"
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
            :disabled="pagination.currentPage >= pagination.lastPage || contactListStore.isLoading"
            @click="goToPage(pagination.currentPage + 1)"
          >
            Próxima
          </BaseButton>
        </div>
      </div>
    </ContentCard>

    <ContactListDrawer
      :open="isModalOpen"
      :list="editingList"
      :contacts="contacts"
      :is-saving="contactListStore.isSaving"
      :is-loading-contacts="contactStore.isLoading"
      :error="contactListStore.error"
      @close="closeModal"
      @submit="handleSubmit"
    />
  </section>
</template>
