import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { Plus, Edit, Trash2, Calculator } from 'lucide-react'
import { fixedAssetsApi } from '@/api/fixedAssets'
import { formatCurrency, formatDate } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Card, CardContent } from '@/components/ui/card'
import StatusBadge from '@/components/ui/StatusBadge'
import Spinner from '@/components/ui/Spinner'
import Pagination from '@/components/ui/Pagination'
import PageHeader from '@/components/layout/PageHeader'
import type { FixedAsset } from '@/types'

export default function FixedAssetsPage() {
  const [page, setPage] = useState(1)
  const qc = useQueryClient()

  const { data, isLoading } = useQuery({
    queryKey: ['fixed-assets', page],
    queryFn: () => fixedAssetsApi.list(page).then((r) => r.data),
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => fixedAssetsApi.destroy(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['fixed-assets'] }),
  })

  const handleDelete = (asset: FixedAsset) => {
    if (confirm(`Hapus aset ${asset.name}?`)) deleteMutation.mutate(asset.id)
  }

  return (
    <div>
      <PageHeader
        title="Aset Tetap"
        description="Kelola aset tetap dan penyusutan"
        action={
          <Button asChild>
            <Link to="/fixed-assets/create"><Plus className="w-4 h-4 mr-2" />Tambah Aset</Link>
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
                    <TableHead>Kode</TableHead>
                    <TableHead>Nama</TableHead>
                    <TableHead>Tgl. Beli</TableHead>
                    <TableHead className="text-right">Harga Beli</TableHead>
                    <TableHead className="text-right">Nilai Buku</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead className="text-right">Aksi</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {data?.data.length === 0 && (
                    <TableRow><TableCell colSpan={7} className="text-center text-gray-400 py-12">Belum ada aset tetap</TableCell></TableRow>
                  )}
                  {data?.data.map((asset) => (
                    <TableRow key={asset.id}>
                      <TableCell className="font-mono text-xs font-medium">{asset.asset_code}</TableCell>
                      <TableCell className="font-medium">{asset.name}</TableCell>
                      <TableCell className="text-sm">{formatDate(asset.acquisition_date)}</TableCell>
                      <TableCell className="text-right">{formatCurrency(asset.cost)}</TableCell>
                      <TableCell className="text-right font-medium">{formatCurrency(asset.book_value)}</TableCell>
                      <TableCell><StatusBadge status={asset.status} /></TableCell>
                      <TableCell>
                        <div className="flex justify-end gap-1">
                          <Button variant="ghost" size="icon" asChild title="Catat Penyusutan">
                            <Link to={`/fixed-assets/${asset.id}/depreciation`}><Calculator className="w-4 h-4" /></Link>
                          </Button>
                          {asset.status !== 'fully_depreciated' && asset.status !== 'disposed' && (
                            <Button variant="ghost" size="icon" asChild>
                              <Link to={`/fixed-assets/${asset.id}/edit`}><Edit className="w-4 h-4" /></Link>
                            </Button>
                          )}
                          <Button variant="ghost" size="icon" className="text-red-500 hover:bg-red-50" onClick={() => handleDelete(asset)}>
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
