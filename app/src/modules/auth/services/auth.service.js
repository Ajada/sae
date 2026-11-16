import { api } from '@/services/http/api'
import { ensureCsrfCookie } from '@/services/http/csrf'

export const authService = {
  async login(payload) {
    await ensureCsrfCookie()

    const { data } = await api.post('/api/v1/auth/login', payload)

    return data
  },

  async me() {
    const { data } = await api.get('/api/v1/auth/me', {
      skipAuthRedirect: true,
    })

    return data
  },

  async logout() {
    const { data } = await api.post('/api/v1/auth/logout')

    return data
  },
}
