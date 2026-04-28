import { useEffect } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Loader2 } from 'lucide-react'
import { fixedAssetsApi } from '@/api/fixedAssets'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import PageHeader from '@/components/layout/PageHeader'
import { formatDateInput } from '@/lib/utils'

const schema = z.object({
  name: z.string().min(1, 'Nama wajib diisi'),
  description: z.string().optional(),
  location: z.string().optional(),
  acquisition_date: z.string().min(1, 'Tanggal perolehan wajib diisi'),
  cost: z.number().gt(0, 'Harga beli harus > 0'),
  salvage_value: z.number().min(0).optional(),
  useful_life_months: z.number().int().min(1, 'Umur ekonomis minimal 1 bulan'),
  depreciation_method: z.enum(['straight_line']).optional(),
  status: z.enum(['active', 'disposed', 'fully_depreciated']).optional(),
})

type FormData = z.infer<typeof schema>

export default function FixedAssetFormPage() {
  const { id } = useParams()
  const isEdit = !!id
  const navigate = useNavigate()
  const qc = useQueryClient()

  const { data: existing } = useQuery({
    queryKey: ['fixed-asset', id],
    queryFn: () => fixedAssetsApi.show(Number(id)).then((r) => r.data.data),
    enabled: isEdit,
  })

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: {
      acquisition_date: formatDateInput(new Date().toISOString()),
      salvage_value: 0,
      depreciation_method: 'straight_line',
      status: 'active',
    },
  })

  useEffect(() => {
    if (existing) {
      reset({
        name: existing.name,
        description: existing.description ?? '',
        location: existing.location ?? '',
        acquisition_date: formatDateInput(existing.acquisition_date),
        cost: existing.cost,
        salvage_value: existing.salvage_value ?? 0,
        useful_life_months: existing.useful_life_months,
        depreciation_method: existing.depreciation_method ?? 'straight_line',
        status: existing.status ?? 'active',
      })
    }
  }, [existing, reset])

  const createMutation = useMutation({
    mutationFn: (d: FormData) => fixedAssetsApi.create(d),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['fixed-assets'] })
      navigate('/fixed-assets')
    },
  })

  const updateMutation = useMutation({
    mutationFn: (d: FormData) => fixedAssetsApi.update(Number(id), d),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['fixed-assets'] })
      navigate('/fixed-assets')
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
      <PageHeader title={isEdit ? 'Edit Aset Tetap' : 'Tambah Aset Tetap'} />

      <form onSubmit={handleSubmit(onSubmit)} className="space-y-6 max-w-2xl">
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Informasi Aset</CardTitle>
          </CardHeader>
          <CardContent className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="space-y-1.5 sm:col-span-2">
              <Label>Nama Aset *</Label>
              <Input placeholder="cth: Mesin Jahit, Laptop, Kendaraan Operasional" {...register('name')} />
              {errors.name && <p className="text-xs text-red-600">{errors.name.message}</p>}
            </div>

            <div className="space-y-1.5">
              <Label>Lokasi</Label>
              <Input placeholder="cth: Gudang Utama, Kantor Lt. 2" {...register('location')} />
            </div>

            <div className="space-y-1.5">
              <Label>Tanggal Perolehan *</Label>
              <Input type="date" {...register('acquisition_date')} />
              {errors.acquisition_date && (
                <p className="text-xs text-red-600">{errors.acquisition_date.message}</p>
              )}
            </div>

            <div className="space-y-1.5 sm:col-span-2">
              <Label>Deskripsi</Label>
              <Textarea rows={2} placeholder="Keterangan atau spesifikasi aset..." {...register('description')} />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">Data Penyusutan</CardTitle>
          </CardHeader>
          <CardContent className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label>Harga Beli / Biaya Perolehan (Rp) *</Label>
              <Input
                type="number"
                step="0.01"
                min="0"
                placeholder="cth: 15000000"
                {...register('cost', { valueAsNumber: true })}
              />
              {errors.cost && <p className="text-xs text-red-600">{errors.cost.message}</p>}
            </div>

            <div className="space-y-1.5">
              <Label>Nilai Sisa / Scrap Value (Rp)</Label>
              <Input
                type="number"
                step="0.01"
                min="0"
                placeholder="cth: 1000000 (opsional)"
                {...register('salvage_value', { valueAsNumber: true })}
              />
              <p className="text-xs text-gray-400">Nilai aset setelah umur ekonomis habis</p>
            </div>

            <div className="space-y-1.5">
              <Label>Umur Ekonomis *</Label>
              <div className="flex gap-2 items-center">
                <Input
                  type="number"
                  min="1"
                  placeholder="cth: 36"
                  {...register('useful_life_months', { valueAsNumber: true })}
                />
                <span className="text-sm text-gray-500 shrink-0">bulan</span>
              </div>
              {errors.useful_life_months && (
                <p className="text-xs text-red-600">{errors.useful_life_months.message}</p>
              )}
              <p className="text-xs text-gray-400">36 bulan = 3 tahun</p>
            </div>

            <div className="space-y-1.5">
              <Label>Metode Penyusutan</Label>
              <select
                className="w-full h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                {...register('depreciation_method')}
              >
                <option value="straight_line">Garis Lurus (Straight Line)</option>
              </select>
            </div>

            {isEdit && (
              <div className="space-y-1.5">
                <Label>Status</Label>
                <select
                  className="w-full h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                  {...register('status')}
                >
                  <option value="active">Aktif</option>
                  <option value="disposed">Dilepas / Dijual</option>
                  <option value="fully_depreciated">Habis Disusutkan</option>
                </select>
              </div>
            )}
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
            {isEdit ? 'Simpan Perubahan' : 'Tambah Aset'}
          </Button>
          <Button type="button" variant="outline" onClick={() => navigate('/fixed-assets')}>
            Batal
          </Button>
        </div>
      </form>
    </div>
  )
}
