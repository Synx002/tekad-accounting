import { useEffect } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Loader2 } from 'lucide-react'
import { inventoryApi } from '@/api/inventory'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import PageHeader from '@/components/layout/PageHeader'

const schema = z.object({
  sku: z.string().max(64).optional(),
  name: z.string().min(1, 'Nama wajib diisi'),
  unit: z.string().max(32).optional(),
  reorder_level: z.number().min(0).optional(),
  notes: z.string().optional(),
})

type FormData = z.infer<typeof schema>

export default function InventoryFormPage() {
  const { id } = useParams()
  const isEdit = !!id
  const navigate = useNavigate()
  const qc = useQueryClient()

  const { data: existing } = useQuery({
    queryKey: ['inventory-item', id],
    queryFn: () => inventoryApi.show(Number(id)).then((r) => r.data.data),
    enabled: isEdit,
  })

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: { reorder_level: 0 },
  })

  useEffect(() => {
    if (existing) {
      reset({
        sku: existing.sku ?? '',
        name: existing.name,
        unit: existing.unit ?? '',
        reorder_level: existing.reorder_level,
        notes: '',
      })
    }
  }, [existing, reset])

  const createMutation = useMutation({
    mutationFn: (d: FormData) => inventoryApi.create(d),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['inventory'] })
      navigate('/inventory')
    },
  })

  const updateMutation = useMutation({
    mutationFn: (d: FormData) => inventoryApi.update(Number(id), d),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['inventory'] })
      navigate('/inventory')
    },
  })

  const onSubmit = (d: FormData) =>
    isEdit ? updateMutation.mutate(d) : createMutation.mutate(d)

  const errorMsg =
    (createMutation.error as { response?: { data?: { message?: string } } })?.response?.data
      ?.message ??
    (updateMutation.error as { response?: { data?: { message?: string } } })?.response?.data
      ?.message

  return (
    <div>
      <PageHeader title={isEdit ? 'Edit Item Inventori' : 'Tambah Item Inventori'} />

      <form onSubmit={handleSubmit(onSubmit)} className="space-y-6 max-w-xl">
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Detail Item</CardTitle>
          </CardHeader>
          <CardContent className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label>Nama Barang *</Label>
              <Input placeholder="cth: Tepung Terigu" {...register('name')} />
              {errors.name && <p className="text-xs text-red-600">{errors.name.message}</p>}
            </div>

            <div className="space-y-1.5">
              <Label>SKU / Kode Barang</Label>
              <Input placeholder="cth: BRG-001 (opsional, bisa dikosongkan)" {...register('sku')} />
              <p className="text-xs text-gray-400">Kosongkan untuk generate otomatis</p>
            </div>

            <div className="space-y-1.5">
              <Label>Satuan</Label>
              <Input placeholder="cth: kg, pcs, liter, dus" {...register('unit')} />
            </div>

            <div className="space-y-1.5">
              <Label>Batas Minimum Stok (Reorder Level)</Label>
              <Input
                type="number"
                step="0.01"
                min="0"
                placeholder="0"
                {...register('reorder_level', { valueAsNumber: true })}
              />
              <p className="text-xs text-gray-400">
                Stok di bawah angka ini akan ditandai ⚠️ Rendah
              </p>
            </div>

            <div className="space-y-1.5 sm:col-span-2">
              <Label>Catatan</Label>
              <Textarea rows={2} placeholder="Keterangan tambahan..." {...register('notes')} />
            </div>
          </CardContent>
        </Card>

        {errorMsg && (
          <div className="rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-700">
            {errorMsg}
          </div>
        )}

        <div className="flex gap-3">
          <Button type="submit" disabled={isSubmitting}>
            {isSubmitting && <Loader2 className="w-4 h-4 mr-2 animate-spin" />}
            {isEdit ? 'Simpan Perubahan' : 'Tambah Item'}
          </Button>
          <Button type="button" variant="outline" onClick={() => navigate('/inventory')}>
            Batal
          </Button>
        </div>
      </form>
    </div>
  )
}
