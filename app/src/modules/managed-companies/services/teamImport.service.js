import { createCrudService } from '@/services/http/createCrudService'

// Reutiliza o cliente HTTP autenticado do X Flow/SAE.
export const teamImportService = {
  upload(companyUuid, file) {
    const body = new FormData()
    body.append('file', file)
    return createCrudService(`/api/v1/managed-companies/${companyUuid}/team-imports`).create(body)
  },
  preview(companyUuid, batchUuid, mapping) {
    return createCrudService(`/api/v1/managed-companies/${companyUuid}/team-imports/${batchUuid}/preview`).create({ mapping })
  },
  commit(companyUuid, batchUuid, rows) {
    return createCrudService(`/api/v1/managed-companies/${companyUuid}/team-imports/${batchUuid}/commit`).create({ rows })
  },
}
