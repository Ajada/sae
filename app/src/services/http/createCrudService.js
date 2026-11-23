import { api } from './api'

export function createCrudService(basePath) {
  return {
    async list(params = {}) {
      const { data } = await api.get(basePath, {
        params,
      })

      return data
    },

    async show(uuid) {
      const { data } = await api.get(`${basePath}/${uuid}`)

      return data
    },

    async create(payload) {
      const { data } = await api.post(basePath, payload)

      return data
    },

    async update(uuid, payload) {
      const { data } = await api.put(`${basePath}/${uuid}`, payload)

      return data
    },

    async remove(uuid) {
      const { data } = await api.delete(`${basePath}/${uuid}`)

      return data
    },

    async action(id, action, payload = {}) {
      return api.post(`${basePath}/${id}/${action}`, payload).then((response) => response.data)
    },
  }
}
