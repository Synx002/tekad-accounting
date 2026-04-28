import api from '@/lib/axios'
import type { Account } from '@/types'

export const accountsApi = {
  list: (params?: { type?: string; active_only?: boolean }) =>
    api.get<{ data: Account[] }>('/accounts', { params }),
}
