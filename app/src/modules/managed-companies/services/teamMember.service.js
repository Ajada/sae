import { createCrudService } from '@/services/http/createCrudService'

export const teamMemberService = createCrudService('/api/v1/team-members')
export const employmentLinkService = createCrudService('/api/v1/employment-links')

export function employeeLinksService(employeeUuid) {
  return createCrudService(`/api/v1/team-members/${employeeUuid}/links`)
}
