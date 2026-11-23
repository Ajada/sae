import { createCrudService } from '@/services/http/createCrudService'

const monitor = createCrudService('/api/v1/aso-monitoring')
export const asoMonitoringService = {
  async report() { return monitor.list() },
}

export const asoService = (companyUuid, linkUuid) => {
  const resource = createCrudService(`/api/v1/managed-companies/${companyUuid}/employment-links/${linkUuid}/asos`)
  return { list: () => resource.list(), create: data => resource.create(data) }
}
