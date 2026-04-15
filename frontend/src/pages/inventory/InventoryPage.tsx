import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { Plus, Edit, Trash2, ArrowUpDown } from 'lucide-react'
import { inventoryApi } from '@/api/inventory'
import { Button } from '@/components/ui/button'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Card, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import Spinner from '@/components/ui/Spinner'
import Pagination from '@/components/ui/Pagination'
import PageHeader from '@/components/layout/PageHeader'
import type { InventoryItem } from '@/types'

export default function InventoryPage() {
  const [page, setPage] = useState(1)
  const qc = useQueryClient()

  const { data, isLoading } = useQuery({
    queryKey: ['inventory', page],
    queryFn: () => inventoryApi.list(page).then((r) => r.data),
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => inventoryApi.destroy(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['inventory'] }),
  })

  const handleDelete = (item: InventoryItem) => {
    if (confirm(`Hapus item ${item.name}?`)) deleteMutation.mutate(item.id)
  }

  return (
    <div>
      <PageHeader
        title="Inventori"
        description="Kelola item stok barang"
        action={
          <Button asChild>
            <Link to="/inventory/create"><Plus className="w-4 h-4 mr-2" />Tambah Item</Link>
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
                    <TableHead>SKU</TableHead>
                    <TableHead>Nama</TableHead>
                    <TableHead>Satuan</TableHead>
                    <TableHead className="text-right">Stok</TableHead>
                    <TableHead className="text-right">Min. Stok</TableHead>
                    <TableHead className="text-right">Aksi</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {data?.data.length === 0 && (
                    <TableRow><TableCell colSpan={6} className="text-center text-gray-400 py-12">Belum ada item</TableCell></TableRow>
                  )}
                  {data?.data.map((item) => (
                    <TableRow key={item.id}>
                      <TableCell className="font-mono text-xs">{item.sku}</TableCell>
                      <TableCell className="font-medium">{item.name}</TableCell>
                      <TableCell className="text-sm text-gray-500">{item.unit}</TableCell>
                      <TableCell className="text-right">
                        <span className={item.quantity_on_hand <= item.reorder_level ? 'text-red-600 font-semibold' : ''}>
                          {item.quantity_on_hand.toLocaleString('id-ID')}
                        </span>
                        {item.quantity_on_hand <= item.reorder_level && (
                          <Badge variant="destructive" className="ml-2 text-xs">Rendah</Badge>
                        )}
                      </TableCell>
                      <TableCell className="text-right text-sm text-gray-500">{item.reorder_level.toLocaleString('id-ID')}</TableCell>
                      <TableCell>
                        <div className="flex justify-end gap-1">
                          <Button variant="ghost" size="icon" asChild>
                            <Link to={`/inventory/${item.id}/movements`}><ArrowUpDown className="w-4 h-4" /></Link>
                          </Button>
                          <Button variant="ghost" size="icon" asChild>
                            <Link to={`/inventory/${item.id}/edit`}><Edit className="w-4 h-4" /></Link>
                          </Button>
                          <Button variant="ghost" size="icon" className="text-red-500 hover:bg-red-50" onClick={() => handleDelete(item)}>
                            <Trash2 className="w-4 h-4" />
                          </Button>
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
