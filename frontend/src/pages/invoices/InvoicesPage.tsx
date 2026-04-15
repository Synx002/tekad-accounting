import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { Plus, Eye, Edit, Trash2, CheckCircle, Loader2 } from 'lucide-react'
import { invoicesApi } from '@/api/invoices'
import { formatCurrency, formatDate } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Card, CardContent } from '@/components/ui/card'
import StatusBadge from '@/components/ui/StatusBadge'
import Spinner from '@/components/ui/Spinner'
import Pagination from '@/components/ui/Pagination'
import PageHeader from '@/components/layout/PageHeader'
import type { Invoice } from '@/types'

export default function InvoicesPage() {
  const [page, setPage] = useState(1)
  const [payingId, setPayingId] = useState<number | null>(null)
  const qc = useQueryClient()

  const { data, isLoading } = useQuery({
    queryKey: ['invoices', page],
    queryFn: () => invoicesApi.list(page).then((r) => r.data),
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => invoicesApi.destroy(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['invoices'] }),
  })

  const payMutation = useMutation({
    mutationFn: (id: number) => invoicesApi.pay(id),
    onSuccess: () => {
      setPayingId(null)
      qc.invalidateQueries({ queryKey: ['invoices'] })
    },
    onError: () => setPayingId(null),
  })

  const handleDelete = (inv: Invoice) => {
    if (confirm(`Hapus invoice ${inv.invoice_number}?`)) {
      deleteMutation.mutate(inv.id)
    }
  }

  const handlePay = (inv: Invoice) => {
    if (confirm(`Tandai ${inv.invoice_number} sebagai LUNAS dan posting jurnal?`)) {
      setPayingId(inv.id)
      payMutation.mutate(inv.id)
    }
  }

  return (
    <div>
      <PageHeader
        title="Invoice"
        description="Kelola invoice penjualan"
        action={
          <Button asChild>
            <Link to="/invoices/create">
              <Plus className="w-4 h-4 mr-2" />
              Buat Invoice
            </Link>
          </Button>
        }
      />

      <Card>
        <CardContent className="p-0">
          {isLoading ? (
            <Spinner />
          ) : (
            <>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Nomor</TableHead>
                    <TableHead>Tanggal</TableHead>
                    <TableHead>Customer</TableHead>
                    <TableHead>Jatuh Tempo</TableHead>
                    <TableHead className="text-right">Total</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead className="text-right">Aksi</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {data?.data.length === 0 && (
                    <TableRow>
                      <TableCell colSpan={7} className="text-center text-gray-400 py-12">
                        Belum ada invoice
                      </TableCell>
                    </TableRow>
                  )}
                  {data?.data.map((inv) => (
                    <TableRow key={inv.id}>
                      <TableCell className="font-mono text-xs font-medium">{inv.invoice_number}</TableCell>
                      <TableCell className="text-sm">{formatDate(inv.invoice_date)}</TableCell>
                      <TableCell className="text-sm font-medium">{inv.customer_name}</TableCell>
                      <TableCell className="text-sm">{formatDate(inv.due_date)}</TableCell>
                      <TableCell className="text-right font-medium">{formatCurrency(inv.grand_total)}</TableCell>
                      <TableCell><StatusBadge status={inv.status} /></TableCell>
                      <TableCell>
                        <div className="flex justify-end gap-1">
                          <Button variant="ghost" size="icon" asChild>
                            <Link to={`/invoices/${inv.id}`}>
                              <Eye className="w-4 h-4" />
                            </Link>
                          </Button>
                          {inv.status !== 'paid' && inv.status !== 'cancelled' && (
                            <>
                              <Button variant="ghost" size="icon" asChild>
                                <Link to={`/invoices/${inv.id}/edit`}>
                                  <Edit className="w-4 h-4" />
                                </Link>
                              </Button>
                              <Button
                                variant="ghost"
                                size="icon"
                                className="text-green-600 hover:text-green-700 hover:bg-green-50"
                                onClick={() => handlePay(inv)}
                                disabled={payingId === inv.id}
                              >
                                {payingId === inv.id ? (
                                  <Loader2 className="w-4 h-4 animate-spin" />
                                ) : (
                                  <CheckCircle className="w-4 h-4" />
                                )}
                              </Button>
                              <Button
                                variant="ghost"
                                size="icon"
                                className="text-red-500 hover:text-red-600 hover:bg-red-50"
                                onClick={() => handleDelete(inv)}
                              >
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
                <Pagination
                  currentPage={data?.current_page ?? 1}
                  lastPage={data?.last_page ?? 1}
                  onPageChange={setPage}
                />
              </div>
            </>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
