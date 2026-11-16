import { createCrudStore } from '@/stores/factories/createCrudStore'
import { contactService } from '../services/contact.service'

export const useContactStore = createCrudStore({
  id: 'contacts',
  service: contactService,
  resourceKeys: ['contact'],
})