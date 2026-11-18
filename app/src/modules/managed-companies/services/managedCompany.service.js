import { createCrudService } from '@/services/http/createCrudService'

const baseService = createCrudService('/api/v1/managed-companies')

export const managedCompanyService = {
  ...baseService,
}
