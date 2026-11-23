import { createCrudStore } from '@/stores/factories/createCrudStore'
import { managedCompanyService } from '../services/managedCompany.service'

export const useManagedCompanyStore = createCrudStore({
  id: 'managedCompanies',
  service: managedCompanyService,
  resourceKeys: ['managed_company'],
})
