import { defineStore } from 'pinia'

function extractResource(response, resourceKeys = []) {
  if (!response) return null

  for (const key of resourceKeys) {
    if (Object.prototype.hasOwnProperty.call(response, key)) {
      return response[key]
    }
  }

  if (response.item) return response.item
  if (response.data && !Array.isArray(response.data)) return response.data

  return response
}

function extractPagination(response) {
  return {
    currentPage: response?.current_page || 1,
    lastPage: response?.last_page || 1,
    perPage: response?.per_page || 15,
    total: response?.total || 0,
    from: response?.from || 0,
    to: response?.to || 0,
  }
}

function extractError(error) {
  return error?.response?.data?.message || 'Não foi possível concluir a operação.'
}

function replaceItem(items, updatedItem) {
  if (!updatedItem) return items

  return items.map((item) => {
    const sameUuid = updatedItem.uuid && item.uuid === updatedItem.uuid
    const sameId = updatedItem.id && item.id === updatedItem.id

    return sameUuid || sameId ? updatedItem : item
  })
}

function getItemKey(item) {
  return item?.uuid || item?.id
}

export function createCrudStore({ id, service, resourceKeys = [] }) {
  return defineStore(id, {
    state: () => ({
      items: [],
      selected: null,

      pagination: {
        currentPage: 1,
        lastPage: 1,
        perPage: 15,
        total: 0,
        from: 0,
        to: 0,
      },

      filters: {
        search: '',
        status: '',
        page: 1,
        per_page: 15,
      },

      isLoading: false,
      isSaving: false,
      isDeleting: false,
      error: null,
    }),

    getters: {
      hasItems: (state) => state.items.length > 0,
      hasError: (state) => Boolean(state.error),
    },

    actions: {
      setFilters(filters = {}) {
        this.filters = {
          ...this.filters,
          ...filters,
        }
      },

      resetFilters() {
        this.filters = {
          search: '',
          status: '',
          page: 1,
          per_page: 15,
        }
      },

      async fetchItems(params = {}) {
        this.isLoading = true
        this.error = null

        try {
          const response = await service.list({
            ...this.filters,
            ...params,
          })

          this.items = response?.data || []
          this.pagination = extractPagination(response)

          return response
        } catch (error) {
          this.error = extractError(error)
          throw error
        } finally {
          this.isLoading = false
        }
      },

      async fetchOne(uuid) {
        this.isLoading = true
        this.error = null

        try {
          const response = await service.show(uuid)
          const resource = extractResource(response, resourceKeys)

          this.selected = resource

          return resource
        } catch (error) {
          this.error = extractError(error)
          throw error
        } finally {
          this.isLoading = false
        }
      },

      async createItem(payload) {
        this.isSaving = true
        this.error = null

        try {
          const response = await service.create(payload)
          const resource = extractResource(response, resourceKeys)

          if (resource) {
            this.selected = resource
            this.items = [resource, ...this.items]
          }

          return response
        } catch (error) {
          this.error = extractError(error)
          throw error
        } finally {
          this.isSaving = false
        }
      },

      async updateItem(uuid, payload) {
        this.isSaving = true
        this.error = null

        try {
          const response = await service.update(uuid, payload)
          const resource = extractResource(response, resourceKeys)

          if (resource) {
            this.selected = resource
            this.items = replaceItem(this.items, resource)
          }

          return response
        } catch (error) {
          this.error = extractError(error)
          throw error
        } finally {
          this.isSaving = false
        }
      },

      async removeItem(uuid) {
        this.isDeleting = true
        this.error = null

        try {
          const response = await service.remove(uuid)

          this.items = this.items.filter((item) => getItemKey(item) !== uuid)

          if (getItemKey(this.selected) === uuid) {
            this.selected = null
          }

          return response
        } catch (error) {
          this.error = extractError(error)
          throw error
        } finally {
          this.isDeleting = false
        }
      },

      clearSelected() {
        this.selected = null
      },

      resetState() {
        this.items = []
        this.selected = null
        this.error = null
        this.resetFilters()
      },
    },
  })
}
