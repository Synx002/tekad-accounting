import api from '@/lib/axios'
import type { JournalEntry, Paginated } from '@/types'

export const journalEntriesApi = {
  list: (page = 1, perPage = 15, status?: string) =>
    api.get<Paginated<JournalEntry>>('/journal-entries', {
      params: { page, per_page: perPage, ...(status ? { status } : {}) },
    }),

  show: (id: number) =>
    api.get<{ data: JournalEntry }>(`/journal-entries/${id}`),

  create: (data: unknown) =>
    api.post<{ data: JournalEntry; message: string }>('/journal-entries', data),

  update: (id: number, data: unknown) =>
    api.put<{ data: JournalEntry; message: string }>(`/journal-entries/${id}`, data),

  destroy: (id: number) =>
    api.delete<{ message: string }>(`/journal-entries/${id}`),

  post: (id: number) =>
    api.post<{ data: JournalEntry; message: string }>(`/journal-entries/${id}/post`),
}
