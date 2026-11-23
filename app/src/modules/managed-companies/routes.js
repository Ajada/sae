export default [
  {
    path: 'acompanhamento/asos',
    name: 'aso-monitoring',
    component: () => import('./pages/AsoMonitoringPage.vue'),
    meta: { title: 'Acompanhamento de ASOs' },
  },
  {
    path: 'empresas',
    name: 'managed-companies',
    component: () => import('./pages/ManagedCompaniesPage.vue'),
    meta: { title: 'Empresas' },
  },
  {
    path: 'empresas/:companyUuid/equipe',
    name: 'managed-company-team',
    component: () => import('./pages/CompanyTeamPage.vue'),
    meta: { title: 'Equipe da empresa' },
  },
  {
    path: 'empresas/:companyUuid/equipe/importar',
    name: 'managed-company-team-import',
    component: () => import('./pages/TeamImportPage.vue'),
    meta: { title: 'Importar colaboradores' },
  },
]
