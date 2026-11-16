import { createCrudService } from '@/services/http/createCrudService'

const baseService = createCrudService('/api/v1/contacts')

export const contactService = {
  ...baseService,
}