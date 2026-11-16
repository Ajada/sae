import authRoutes from '@/modules/auth/routes'
import dashboardRoutes from '@/modules/dashboard/routes'
import contactRoutes from '@/modules/contacts/routes'

import PublicLayout from '@/layouts/PublicLayout.vue'
import AppLayout from '@/layouts/AppLayout.vue'

export const routes = [
  {
    path: '/',
    component: PublicLayout,
    children: [
      {
        path: '',
        name: 'landing',
        component: () => import('@/modules/public/pages/LandingPage.vue'),
        meta: {
          public: true,
        },
      },
    ],
  },

  ...authRoutes,

  {
    path: '/app',
    component: AppLayout,
    meta: {
      requiresAuth: true,
    },
    children: [
      ...dashboardRoutes,
      ...contactRoutes,
    ],
  },
]