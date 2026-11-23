import { createCrudStore } from '@/stores/factories/createCrudStore'
import { teamMemberService } from '../services/teamMember.service'

export const useTeamMemberStore = createCrudStore({
  id: 'teamMembers',
  service: teamMemberService,
  resourceKeys: ['employee'],
})
