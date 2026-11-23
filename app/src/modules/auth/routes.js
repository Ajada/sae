import AuthLayout from '@/layouts/AuthLayout.vue'

export default [
  {
    path: '/login',
    component: AuthLayout,
    meta: {
      guestOnly: true,
    },
    children: [
      {
        path: '',
        name: 'login',
        component: () => import('./pages/LoginPage.vue'),
      },
    ],
  },
]