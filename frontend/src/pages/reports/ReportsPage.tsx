import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { reportsApi } from '@/api/reports'
import { formatCurrency } from '@/lib/utils'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import Spinner from '@/components/ui/Spinner'
import PageHeader from '@/components/layout/PageHeader'
import type { AccountBalance } from '@/types'

const today = new Date().toISOString().split('T')[0]
const firstOfMonth = `${today.slice(0, 7)}-01`

export default function ReportsPage() {
  const [activeTab, setActiveTab] = useState<'income' | 'balance'>('income')
  const [startDate, setStartDate] = useState(firstOfMonth)
  const [endDate, setEndDate] = useState(today)
  const [asOf, setAsOf] = useState(today)
  const [queryParams, setQueryParams] = useState({ startDate: firstOfMonth, endDate: today, asOf: today })

  const incomeQuery = useQuery({
    queryKey: ['income-statement', queryParams.startDate, queryParams.endDate],
    queryFn: () => reportsApi.incomeStatement(queryParams.startDate, queryParams.endDate).then((r) => r.data),
    enabled: activeTab === 'income',
  })

  const balanceQuery = useQuery({
    queryKey: ['balance-sheet', queryParams.asOf],
    queryFn: () => reportsApi.balanceSheet(queryParams.asOf).then((r) => r.data),
    enabled: activeTab === 'balance',
  })

  const handleGenerate = () => setQueryParams({ startDate, endDate, asOf })

  return (
    <div className="space-y-6">
      <PageHeader title="Laporan Keuangan" description="Laba Rugi & Neraca dari data jurnal" />

      {/* Tab */}
      <div className="flex gap-1 bg-gray-100 p-1 rounded-lg w-fit">
        {[
          { key: 'income', label: 'Laba Rugi' },
          { key: 'balance', label: 'Neraca' },
        ].map(({ key, label }) => (
          <button
            key={key}
            onClick={() => setActiveTab(key as 'income' | 'balance')}
            className={`px-4 py-1.5 rounded-md text-sm font-medium transition-all ${
              activeTab === key ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700'
            }`}
          >
            {label}
          </button>
        ))}
      </div>

      {/* Filter */}
      <Card className="shadow-sm border border-gray-200 rounded-xl">
        <CardContent className="pt-4">
          <div className="flex flex-wrap gap-4 items-end">
            {activeTab === 'income' ? (
              <>
                <div className="space-y-1.5">
                  <Label className="text-xs font-medium text-gray-500 uppercase tracking-wide">Dari</Label>
                  <Input type="date" value={startDate} onChange={(e) => setStartDate(e.target.value)} className="w-40" />
                </div>
                <div className="space-y-1.5">
                  <Label className="text-xs font-medium text-gray-500 uppercase tracking-wide">Sampai</Label>
                  <Input type="date" value={endDate} onChange={(e) => setEndDate(e.target.value)} className="w-40" />
                </div>
              </>
            ) : (
              <div className="space-y-1.5">
                <Label className="text-xs font-medium text-gray-500 uppercase tracking-wide">Per Tanggal</Label>
                <Input type="date" value={asOf} onChange={(e) => setAsOf(e.target.value)} className="w-40" />
              </div>
            )}
            <Button className="shadow-sm" onClick={handleGenerate}>Tampilkan</Button>
          </div>
        </CardContent>
      </Card>

      {/* Income Statement */}
      {activeTab === 'income' && (
        incomeQuery.isLoading ? (
          <div className="flex items-center justify-center py-24"><Spinner /></div>
        ) : incomeQuery.data ? (
          <div className="space-y-4">
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <SummaryCard label="Total Pendapatan" value={incomeQuery.data.revenue.total_revenue} color="text-green-700" bg="border-gray-200" />
              <SummaryCard label="Total Beban" value={incomeQuery.data.expense.total_expense} color="text-red-600" bg="border-gray-200" />
              <SummaryCard
                label={incomeQuery.data.is_profit ? 'Laba Bersih' : 'Rugi Bersih'}
                value={incomeQuery.data.net_income}
                color={incomeQuery.data.is_profit ? 'text-blue-700' : 'text-red-700'}
                bg="border-gray-200"  
              />
            </div>
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
              <AccountTable title="Pendapatan" rows={incomeQuery.data.revenue.accounts} total={incomeQuery.data.revenue.total_revenue} totalLabel="Total Pendapatan" />
              <AccountTable title="Beban" rows={incomeQuery.data.expense.accounts} total={incomeQuery.data.expense.total_expense} totalLabel="Total Beban" />
            </div>
          </div>
        ) : null
      )}

      {/* Balance Sheet */}
      {activeTab === 'balance' && (
        balanceQuery.isLoading ? (
          <div className="flex items-center justify-center py-24"><Spinner /></div>
        ) : balanceQuery.data ? (
          <div className="space-y-4">
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <SummaryCard label="Total Aset" value={balanceQuery.data.assets.total_assets} color="text-blue-700" bg="border-gray-200" />
              <SummaryCard label="Total Kewajiban" value={balanceQuery.data.liabilities.total_liabilities} color="text-red-600" bg="border-gray-200" />
              <SummaryCard label="Total Ekuitas" value={balanceQuery.data.equity.total_equity} color="text-emerald-600" bg="border-gray-200" />
            </div>
            {!balanceQuery.data.is_balanced && (
              <div className="bg-yellow-50 border border-yellow-200 rounded-xl p-4 text-sm text-yellow-800">
                ⚠️ Neraca tidak seimbang. Periksa jurnal yang belum diposting.
              </div>
            )}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
              <AccountTable title="Aset" rows={balanceQuery.data.assets.accounts} total={balanceQuery.data.assets.total_assets} totalLabel="Total Aset" />
              <div className="space-y-4">
                <AccountTable title="Kewajiban" rows={balanceQuery.data.liabilities.accounts} total={balanceQuery.data.liabilities.total_liabilities} totalLabel="Total Kewajiban" />
                <AccountTable title="Ekuitas" rows={balanceQuery.data.equity.accounts} total={balanceQuery.data.equity.total_equity} totalLabel="Total Ekuitas" />
              </div>
            </div>
          </div>
        ) : null
      )}
    </div>
  )
}

function SummaryCard({
  label, value, color, bg, highlight,
}: {
  label: string
  value: number
  color: string
  bg: string
  highlight?: boolean
}) {
  return (
    <Card className={`shadow-sm border rounded-xl ${bg} ${highlight ? 'ring-1 ring-blue-200' : ''}`}>
      <CardContent className="pt-4 pb-4">
        <p className="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">{label}</p>
        <p className={`text-xl font-bold tabular-nums ${color}`}>{formatCurrency(value)}</p>
      </CardContent>
    </Card>
  )
}

function AccountTable({
  title, rows, total, totalLabel,
}: {
  title: string
  rows: AccountBalance[]
  total: number
  totalLabel: string
}) {
  return (
    <Card className="shadow-sm border border-gray-200 rounded-xl overflow-hidden flex flex-col">
      <CardHeader className="border-b border-gray-100 py-3 px-6">
        <CardTitle className="text-sm font-semibold text-gray-700">{title}</CardTitle>
      </CardHeader>
      <CardContent className="p-0 flex flex-col flex-1">
        {/* Scrollable area */}
        <div className="overflow-auto flex-1">
          <Table>
            <TableHeader>
              <TableRow className="bg-gray-50 border-b border-gray-200 hover:bg-gray-50">
                <TableHead className="text-xs font-semibold text-gray-500 uppercase tracking-wide pl-6 py-2.5 w-[120px]">
                  Kode
                </TableHead>
                <TableHead className="text-xs font-semibold text-gray-500 uppercase tracking-wide py-2.5">
                  Akun
                </TableHead>
                <TableHead className="text-xs font-semibold text-gray-500 uppercase tracking-wide pr-6 py-2.5 text-right">
                  Saldo
                </TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {rows.length === 0 && (
                <TableRow>
                  <TableCell colSpan={3} className="text-center text-gray-400 py-8 text-sm">
                    Tidak ada aktivitas
                  </TableCell>
                </TableRow>
              )}
              {rows.map((r, index) => (
                <TableRow
                  key={r.code}
                  className={`
                    border-b border-gray-100 transition-colors duration-100
                    hover:bg-blue-50/40
                    ${index % 2 === 0 ? 'bg-white' : 'bg-gray-50/30'}
                  `}
                >
                  <TableCell className="pl-6 py-2.5">
                    <span className="font-mono text-xs font-semibold text-blue-700 bg-blue-50 px-2 py-0.5 rounded-md border border-blue-100">
                      {r.code}
                    </span>
                  </TableCell>
                  <TableCell className="py-2.5 text-sm text-gray-700">{r.name}</TableCell>
                  <TableCell className="pr-6 py-2.5 text-right text-sm font-medium text-gray-900 tabular-nums">
                    {formatCurrency(r.balance)}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>

        {/* Total — sticky di bawah */}
        <div className="sticky bottom-0 border-t-2 border-gray-200 bg-gray-50 px-6 py-3 flex items-center justify-between">
          <span className="text-sm font-semibold text-gray-700">{totalLabel}</span>
          <span className="text-sm font-bold text-gray-900 tabular-nums">{formatCurrency(total)}</span>
        </div>
      </CardContent>
    </Card>
  )
}