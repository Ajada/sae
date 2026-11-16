import { createCrudStore } from '@/stores/factories/createCrudStore'
import { contactListService } from '../services/contactList.service'

export const useContactListStore = createCrudStore({
  id: 'contactLists',
  service: contactListService,
  resourceKeys: ['contact_list', 'list'],
})