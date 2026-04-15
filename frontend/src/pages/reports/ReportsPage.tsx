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
    <div>
      <PageHeader title="Laporan Keuangan" description="Laba Rugi & Neraca dari data jurnal" />

      {/* Tab */}
      <div className="flex gap-1 bg-gray-100 p-1 rounded-lg w-fit mb-6">
        {[
          { key: 'income', label: 'Laba Rugi' },
          { key: 'balance', label: 'Neraca' },
        ].map(({ key, label }) => (
          <button
            key={key}
            onClick={() => setActiveTab(key as 'income' | 'balance')}
            className={`px-4 py-1.5 rounded-md text-sm font-medium transition-all ${activeTab === key ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700'}`}
          >
            {label}
          </button>
        ))}
      </div>

      {/* Filter */}
      <Card className="mb-6">
        <CardContent className="pt-4">
          <div className="flex flex-wrap gap-4 items-end">
            {activeTab === 'income' ? (
              <>
                <div className="space-y-1">
                  <Label className="text-xs">Dari</Label>
                  <Input type="date" value={startDate} onChange={(e) => setStartDate(e.target.value)} className="w-40" />
                </div>
                <div className="space-y-1">
                  <Label className="text-xs">Sampai</Label>
                  <Input type="date" value={endDate} onChange={(e) => setEndDate(e.target.value)} className="w-40" />
                </div>
              </>
            ) : (
              <div className="space-y-1">
                <Label className="text-xs">Per Tanggal</Label>
                <Input type="date" value={asOf} onChange={(e) => setAsOf(e.target.value)} className="w-40" />
              </div>
            )}
            <Button onClick={handleGenerate}>Tampilkan</Button>
          </div>
        </CardContent>
      </Card>

      {/* Income Statement */}
      {activeTab === 'income' && (
        incomeQuery.isLoading ? <Spinner /> : incomeQuery.data ? (
          <div className="space-y-4">
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <SummaryCard label="Total Pendapatan" value={incomeQuery.data.revenue.total_revenue} color="text-green-700" />
              <SummaryCard label="Total Beban" value={incomeQuery.data.expense.total_expense} color="text-red-600" />
              <SummaryCard
                label={incomeQuery.data.is_profit ? 'Laba Bersih' : 'Rugi Bersih'}
                value={incomeQuery.data.net_income}
                color={incomeQuery.data.is_profit ? 'text-blue-700' : 'text-red-700'}
                highlight
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
        balanceQuery.isLoading ? <Spinner /> : balanceQuery.data ? (
          <div className="space-y-4">
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <SummaryCard label="Total Aset" value={balanceQuery.data.assets.total_assets} color="text-blue-700" />
              <SummaryCard label="Total Kewajiban" value={balanceQuery.data.liabilities.total_liabilities} color="text-red-600" />
              <SummaryCard label="Total Ekuitas" value={balanceQuery.data.equity.total_equity} color="text-green-700" />
            </div>
            {!balanceQuery.data.is_balanced && (
              <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-sm text-yellow-800">
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

function SummaryCard({ label, value, color, highlight }: { label: string; value: number; color: string; highlight?: boolean }) {
  return (
    <Card className={highlight ? 'border-blue-200 bg-blue-50' : ''}>
      <CardContent className="pt-4">
        <p className="text-xs text-gray-500 mb-1">{label}</p>
        <p className={`text-xl font-bold ${color}`}>{formatCurrency(value)}</p>
      </CardContent>
    </Card>
  )
}

function AccountTable({ title, rows, total, totalLabel }: { title: string; rows: AccountBalance[]; total: number; totalLabel: string }) {
  return (
    <Card>
      <CardHeader className="pb-2"><CardTitle className="text-sm font-semibold">{title}</CardTitle></CardHeader>
      <CardContent className="p-0">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="text-xs">Kode</TableHead>
              <TableHead className="text-xs">Akun</TableHead>
              <TableHead className="text-right text-xs">Saldo</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {rows.length === 0 && (
              <TableRow><TableCell colSpan={3} className="text-center text-gray-400 py-6 text-sm">Tidak ada aktivitas</TableCell></TableRow>
            )}
            {rows.map((r) => (
              <TableRow key={r.code}>
                <TableCell className="font-mono text-xs py-2">{r.code}</TableCell>
                <TableCell className="text-sm py-2">{r.name}</TableCell>
                <TableCell className="text-right text-sm py-2">{formatCurrency(r.balance)}</TableCell>
              </TableRow>
            ))}
            <TableRow className="bg-gray-50 font-semibold">
              <TableCell colSpan={2} className="py-2 text-sm">{totalLabel}</TableCell>
              <TableCell className="text-right py-2 text-sm">{formatCurrency(total)}</TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </CardContent>
    </Card>
  )
}
