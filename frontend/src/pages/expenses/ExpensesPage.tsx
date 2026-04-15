import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { Plus, Edit, Trash2, CheckCircle, Loader2 } from 'lucide-react'
import { expensesApi } from '@/api/expenses'
import { formatCurrency, formatDate } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Card, CardContent } from '@/components/ui/card'
import StatusBadge from '@/components/ui/StatusBadge'
import Spinner from '@/components/ui/Spinner'
import Pagination from '@/components/ui/Pagination'
import PageHeader from '@/components/layout/PageHeader'
import type { Expense } from '@/types'

export default function ExpensesPage() {
  const [page, setPage] = useState(1)
  const [payingId, setPayingId] = useState<number | null>(null)
  const qc = useQueryClient()

  const { data, isLoading } = useQuery({
    queryKey: ['expenses', page],
    queryFn: () => expensesApi.list(page).then((r) => r.data),
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => expensesApi.destroy(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['expenses'] }),
  })

  const payMutation = useMutation({
    mutationFn: (id: number) => expensesApi.pay(id),
    onSuccess: () => { setPayingId(null); qc.invalidateQueries({ queryKey: ['expenses'] }) },
    onError: () => setPayingId(null),
  })

  const handleDelete = (e: Expense) => {
    if (confirm(`Hapus biaya ${e.expense_number}?`)) deleteMutation.mutate(e.id)
  }
  const handlePay = (e: Expense) => {
    if (confirm(`Tandai ${e.expense_number} sebagai LUNAS dan posting jurnal?`)) {
      setPayingId(e.id); payMutation.mutate(e.id)
    }
  }

  return (
    <div>
      <PageHeader
        title="Biaya (Expense)"
        description="Kelola pengeluaran operasional"
        action={
          <Button asChild>
            <Link to="/expenses/create"><Plus className="w-4 h-4 mr-2" />Catat Biaya</Link>
          </Button>
        }
      />
      <Card>
        <CardContent className="p-0">
          {isLoading ? <Spinner /> : (
            <>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Nomor</TableHead>
                    <TableHead>Tanggal</TableHead>
                    <TableHead>Kategori</TableHead>
                    <TableHead>Penerima</TableHead>
                    <TableHead className="text-right">Total</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead className="text-right">Aksi</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {data?.data.length === 0 && (
                    <TableRow><TableCell colSpan={7} className="text-center text-gray-400 py-12">Belum ada biaya</TableCell></TableRow>
                  )}
                  {data?.data.map((e) => (
                    <TableRow key={e.id}>
                      <TableCell className="font-mono text-xs font-medium">{e.expense_number}</TableCell>
                      <TableCell className="text-sm">{formatDate(e.expense_date)}</TableCell>
                      <TableCell><span className="text-xs bg-gray-100 text-gray-700 px-2 py-0.5 rounded-full">{e.category}</span></TableCell>
                      <TableCell className="text-sm">{e.payee_name ?? '-'}</TableCell>
                      <TableCell className="text-right font-medium">{formatCurrency(e.grand_total)}</TableCell>
                      <TableCell><StatusBadge status={e.status} /></TableCell>
                      <TableCell>
                        <div className="flex justify-end gap-1">
                          {e.status !== 'paid' && e.status !== 'cancelled' && (
                            <>
                              <Button variant="ghost" size="icon" asChild>
                                <Link to={`/expenses/${e.id}/edit`}><Edit className="w-4 h-4" /></Link>
                              </Button>
                              <Button variant="ghost" size="icon" className="text-green-600 hover:bg-green-50"
                                onClick={() => handlePay(e)} disabled={payingId === e.id}>
                                {payingId === e.id ? <Loader2 className="w-4 h-4 animate-spin" /> : <CheckCircle className="w-4 h-4" />}
                              </Button>
                              <Button variant="ghost" size="icon" className="text-red-500 hover:bg-red-50" onClick={() => handleDelete(e)}>
                                <Trash2 className="w-4 h-4" />
                              </Button>
                            </>
                          )}
                        </div>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
              <div className="px-4 pb-4">
                <Pagination currentPage={data?.current_page ?? 1} lastPage={data?.last_page ?? 1} onPageChange={setPage} />
              </div>
            </>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
