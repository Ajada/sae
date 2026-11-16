export default [
  {
    path: 'dashboard',
    name: 'dashboard',
    component: () => import('./pages/DashboardPage.vue'),
  },
  {
    path: '',
    redirect: {
      name: 'dashboard',
    },
  },
]