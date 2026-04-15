import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { Plus, Edit, Trash2, CheckCircle, Loader2 } from 'lucide-react'
import { purchaseBillsApi } from '@/api/purchaseBills'
import { formatCurrency, formatDate } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Card, CardContent } from '@/components/ui/card'
import StatusBadge from '@/components/ui/StatusBadge'
import Spinner from '@/components/ui/Spinner'
import Pagination from '@/components/ui/Pagination'
import PageHeader from '@/components/layout/PageHeader'
import type { PurchaseBill } from '@/types'

export default function PurchaseBillsPage() {
  const [page, setPage] = useState(1)
  const [payingId, setPayingId] = useState<number | null>(null)
  const qc = useQueryClient()

  const { data, isLoading } = useQuery({
    queryKey: ['purchase-bills', page],
    queryFn: () => purchaseBillsApi.list(page).then((r) => r.data),
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => purchaseBillsApi.destroy(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['purchase-bills'] }),
  })

  const payMutation = useMutation({
    mutationFn: (id: number) => purchaseBillsApi.pay(id),
    onSuccess: () => { setPayingId(null); qc.invalidateQueries({ queryKey: ['purchase-bills'] }) },
    onError: () => setPayingId(null),
  })

  const handleDelete = (b: PurchaseBill) => {
    if (confirm(`Hapus purchase bill ${b.purchase_number}?`)) deleteMutation.mutate(b.id)
  }
  const handlePay = (b: PurchaseBill) => {
    if (confirm(`Tandai ${b.purchase_number} sebagai LUNAS dan posting jurnal?`)) {
      setPayingId(b.id); payMutation.mutate(b.id)
    }
  }

  return (
    <div>
      <PageHeader
        title="Purchase Bill"
        description="Kelola tagihan pembelian"
        action={
          <Button asChild>
            <Link to="/purchase-bills/create"><Plus className="w-4 h-4 mr-2" />Buat Bill</Link>
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
                    <TableHead>Supplier</TableHead>
                    <TableHead className="text-right">Total</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead className="text-right">Aksi</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {data?.data.length === 0 && (
                    <TableRow><TableCell colSpan={6} className="text-center text-gray-400 py-12">Belum ada purchase bill</TableCell></TableRow>
                  )}
                  {data?.data.map((b) => (
                    <TableRow key={b.id}>
                      <TableCell className="font-mono text-xs font-medium">{b.purchase_number}</TableCell>
                      <TableCell className="text-sm">{formatDate(b.purchase_date)}</TableCell>
                      <TableCell className="text-sm font-medium">{b.supplier_name}</TableCell>
                      <TableCell className="text-right font-medium">{formatCurrency(b.grand_total)}</TableCell>
                      <TableCell><StatusBadge status={b.status} /></TableCell>
                      <TableCell>
                        <div className="flex justify-end gap-1">
                          {b.status !== 'paid' && b.status !== 'cancelled' && (
                            <>
                              <Button variant="ghost" size="icon" asChild>
                                <Link to={`/purchase-bills/${b.id}/edit`}><Edit className="w-4 h-4" /></Link>
                              </Button>
                              <Button variant="ghost" size="icon" className="text-green-600 hover:bg-green-50"
                                onClick={() => handlePay(b)} disabled={payingId === b.id}>
                                {payingId === b.id ? <Loader2 className="w-4 h-4 animate-spin" /> : <CheckCircle className="w-4 h-4" />}
                              </Button>
                              <Button variant="ghost" size="icon" className="text-red-500 hover:bg-red-50"
                                onClick={() => handleDelete(b)}>
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
