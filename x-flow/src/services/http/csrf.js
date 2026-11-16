import { api } from './api'

export async function ensureCsrfCookie() {
  await api.get('/sanctum/csrf-cookie')
}