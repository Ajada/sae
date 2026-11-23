import { createCrudService } from '@/services/http/createCrudService'

const baseService = createCrudService('/api/v1/contact-lists')

export const contactListService = {
  ...baseService,

  async attachContacts(listUuid, payload) {
    return baseService.update(listUuid, payload)
  },
}