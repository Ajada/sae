import axios from 'axios'

import { env } from '@/app/config/env'

export const api = axios.create({
  baseURL: env.apiUrl,
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error?.response?.status
    const skipAuthRedirect = error?.config?.skipAuthRedirect === true

    if (status === 401 && !skipAuthRedirect) {
      window.dispatchEvent(new CustomEvent('auth:unauthenticated'))
    }

    return Promise.reject(error)
  },
)