import api from '@/lib/axios'
import type { User } from '@/types'

export const authApi = {
  login: (email: string, password: string) =>
    api.post<{ token: string; user: User }>('/login', { email, password }),

  logout: () => api.post('/logout'),

  me: () => api.get<{ user: User }>('/me'),
}
