import { useEffect } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Loader2 } from 'lucide-react'
import { expensesApi } from '@/api/expenses'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import PageHeader from '@/components/layout/PageHeader'
import { formatCurrency } from '@/lib/utils'

const schema = z.object({
  expense_date: z.string().min(1),
  category: z.string().min(1),
  payee_name: z.string().optional(),
  description: z.string().min(1),
  subtotal: z.number().gt(0),
  tax_percent: z.number().min(0).max(100),
  reference: z.string().optional(),
  notes: z.string().optional(),
})

type FormData = z.infer<typeof schema>

export default function ExpenseFormPage() {
  const { id } = useParams()
  const isEdit = !!id
  const navigate = useNavigate()
  const qc = useQueryClient()

  const { data: existing } = useQuery({
    queryKey: ['expense', id],
    queryFn: () => expensesApi.show(Number(id)).then((r) => r.data.data),
    enabled: isEdit,
  })

  const { register, handleSubmit, watch, reset, formState: { errors, isSubmitting } } =
    useForm<FormData>({ resolver: zodResolver(schema), defaultValues: { tax_percent: 0 } })

  useEffect(() => {
    if (existing) {
      const taxPercent = Number(existing.subtotal) > 0
        ? Math.round((Number(existing.tax_total) / Number(existing.subtotal)) * 100)
        : 0
      reset({
        expense_date: existing.expense_date,
        category: existing.category,
        payee_name: existing.payee_name ?? '',
        description: existing.description,
        subtotal: Number(existing.subtotal),
        tax_percent: taxPercent,
        reference: existing.reference ?? '',
        notes: existing.notes ?? '',
      })
    }
  }, [existing, reset])

  const createMutation = useMutation({
    mutationFn: (d: FormData) => expensesApi.create(d),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['expenses'] }); navigate('/expenses') },
  })
  const updateMutation = useMutation({
    mutationFn: (d: FormData) => expensesApi.update(Number(id), d),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['expenses'] }); navigate('/expenses') },
  })

  const onSubmit = (d: FormData) => isEdit ? updateMutation.mutate(d) : createMutation.mutate(d)

  const subtotal = watch('subtotal') ?? 0
  const taxPercent = watch('tax_percent') ?? 0
  const taxTotal = subtotal * (taxPercent / 100)

  return (
    <div>
      <PageHeader title={isEdit ? 'Edit Biaya' : 'Catat Biaya'} />
      <form onSubmit={handleSubmit(onSubmit)} className="space-y-6 max-w-2xl">
        <Card>
          <CardHeader><CardTitle className="text-base">Detail Biaya</CardTitle></CardHeader>
          <CardContent className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label>Tanggal *</Label>
              <Input type="date" {...register('expense_date')} />
            </div>
            <div className="space-y-1.5">
              <Label>Kategori *</Label>
              <Input placeholder="cth: Operasional, Transport, Utilitas" {...register('category')} />
              {errors.category && <p className="text-xs text-red-600">{errors.category.message}</p>}
            </div>
            <div className="space-y-1.5">
              <Label>Penerima / Vendor</Label>
              <Input placeholder="Nama vendor / penerima" {...register('payee_name')} />
            </div>
            <div className="space-y-1.5">
              <Label>Referensi</Label>
              <Input placeholder="No. kwitansi / referensi" {...register('reference')} />
            </div>
            <div className="space-y-1.5 sm:col-span-2">
              <Label>Keterangan *</Label>
              <Input placeholder="Deskripsi singkat biaya" {...register('description')} />
              {errors.description && <p className="text-xs text-red-600">{errors.description.message}</p>}
            </div>
            <div className="space-y-1.5">
              <Label>Jumlah (sebelum pajak) *</Label>
              <Input type="number" step="0.01" {...register('subtotal', { valueAsNumber: true })} />
              {errors.subtotal && <p className="text-xs text-red-600">{errors.subtotal.message}</p>}
            </div>
            <div className="space-y-1.5">
              <Label>PPN (%)</Label>
              <Input type="number" step="0.01" min="0" max="100" {...register('tax_percent', { valueAsNumber: true })} />
            </div>
            <div className="space-y-1.5 sm:col-span-2">
              <Label>Catatan</Label>
              <Textarea rows={2} {...register('notes')} />
            </div>
          </CardContent>
        </Card>

        {/* Total preview */}
        <Card className="bg-gray-50">
          <CardContent className="pt-4 space-y-1 text-sm">
            <div className="flex justify-between text-gray-600"><span>Subtotal</span><span>{formatCurrency(subtotal)}</span></div>
            <div className="flex justify-between text-gray-600"><span>PPN ({taxPercent}%)</span><span>{formatCurrency(taxTotal)}</span></div>
            <div className="flex justify-between font-bold text-gray-900"><span>Total</span><span>{formatCurrency(subtotal + taxTotal)}</span></div>
          </CardContent>
        </Card>

        <div className="flex gap-3">
          <Button type="submit" disabled={isSubmitting}>
            {isSubmitting && <Loader2 className="w-4 h-4 mr-2 animate-spin" />}
            {isEdit ? 'Simpan' : 'Catat Biaya'}
          </Button>
          <Button type="button" variant="outline" onClick={() => navigate('/expenses')}>Batal</Button>
        </div>
      </form>
    </div>
  )
}
