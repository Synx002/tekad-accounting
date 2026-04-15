import api from '@/lib/axios'
import type { BalanceSheet, IncomeStatement } from '@/types'

export const reportsApi = {
  incomeStatement: (startDate: string, endDate: string) =>
    api.get<IncomeStatement>('/reports/income-statement', {
      params: { start_date: startDate, end_date: endDate },
    }),

  balanceSheet: (asOf?: string) =>
    api.get<BalanceSheet>('/reports/balance-sheet', {
      params: asOf ? { as_of: asOf } : {},
    }),

  financial: (startDate: string, endDate: string) =>
    api.get('/reports/financial', {
      params: { start_date: startDate, end_date: endDate },
    }),
}
