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
  correct(companyUuid, batchUuid, row, data) {
    return createCrudService(`/api/v1/managed-companies/${companyUuid}/team-imports/${batchUuid}/corrections`).create({ row, data })
  },
  asoPreview(companyUuid, batchUuid, periodicityMonths) {
    return createCrudService(`/api/v1/managed-companies/${companyUuid}/team-imports/${batchUuid}/asos/preview`).create({ periodicity_months: periodicityMonths })
  },
  asoCommit(companyUuid, batchUuid, payload) {
    return createCrudService(`/api/v1/managed-companies/${companyUuid}/team-imports/${batchUuid}/asos/commit`).create(payload)
  },
  commit(companyUuid, batchUuid, rows) {
    return createCrudService(`/api/v1/managed-companies/${companyUuid}/team-imports/${batchUuid}/commit`).create({ rows })
  },
}
