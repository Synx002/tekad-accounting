import { useEffect } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useForm, useFieldArray } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Trash2, Loader2 } from 'lucide-react'
import { invoicesApi } from '@/api/invoices'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import PageHeader from '@/components/layout/PageHeader'
import { formatCurrency } from '@/lib/utils'

const itemSchema = z.object({
  description: z.string().min(1, 'Wajib diisi'),
  quantity: z.number().gt(0, 'Harus > 0'),
  unit_price: z.number().gte(0, 'Harus >= 0'),
  tax_percent: z.number().min(0).max(100),
})

const schema = z.object({
  invoice_date: z.string().min(1, 'Wajib diisi'),
  due_date: z.string().optional(),
  customer_name: z.string().min(1, 'Wajib diisi'),
  status: z.enum(['draft', 'sent', 'paid', 'cancelled']),
  notes: z.string().optional(),
  items: z.array(itemSchema).min(1, 'Minimal 1 item'),
})

type FormData = z.infer<typeof schema>

export default function InvoiceFormPage() {
  const { id } = useParams()
  const isEdit = !!id
  const navigate = useNavigate()
  const qc = useQueryClient()

  const { data: existing } = useQuery({
    queryKey: ['invoice', id],
    queryFn: () => invoicesApi.show(Number(id)).then((r) => r.data.data),
    enabled: isEdit,
  })

  const {
    register,
    handleSubmit,
    control,
    watch,
    setValue,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: { status: 'draft', items: [{ description: '', quantity: 1, unit_price: 0, tax_percent: 0 }] },
  })

  const { fields, append, remove } = useFieldArray({ control, name: 'items' })

  useEffect(() => {
    if (existing) {
      reset({
        invoice_date: existing.invoice_date,
        due_date: existing.due_date ?? '',
        customer_name: existing.customer_name,
        status: existing.status,
        notes: existing.notes ?? '',
        items: existing.items.map((it) => ({
          description: it.description,
          quantity: it.quantity,
          unit_price: Number(it.unit_price),
          tax_percent: it.tax_percent,
        })),
      })
    }
  }, [existing, reset])

  const createMutation = useMutation({
    mutationFn: (data: FormData) => invoicesApi.create(data),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['invoices'] }); navigate('/invoices') },
  })

  const updateMutation = useMutation({
    mutationFn: (data: FormData) => invoicesApi.update(Number(id), data),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['invoices'] }); navigate('/invoices') },
  })

  const onSubmit = (data: FormData) => {
    if (isEdit) updateMutation.mutate(data)
    else createMutation.mutate(data)
  }

  const items = watch('items')
  const subtotal = items.reduce((s, it) => s + it.quantity * it.unit_price, 0)
  const taxTotal = items.reduce((s, it) => s + it.quantity * it.unit_price * (it.tax_percent / 100), 0)

  return (
    <div>
      <PageHeader title={isEdit ? 'Edit Invoice' : 'Buat Invoice'} />

      <form onSubmit={handleSubmit(onSubmit)} className="space-y-6 max-w-3xl">
        <Card>
          <CardHeader><CardTitle className="text-base">Informasi Invoice</CardTitle></CardHeader>
          <CardContent className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label>Customer *</Label>
              <Input placeholder="Nama customer" {...register('customer_name')} />
              {errors.customer_name && <p className="text-xs text-red-600">{errors.customer_name.message}</p>}
            </div>

            <div className="space-y-1.5">
              <Label>Status</Label>
              <Select
                defaultValue="draft"
                onValueChange={(v) => setValue('status', v as FormData['status'])}
                value={watch('status')}
              >
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="draft">Draf</SelectItem>
                  <SelectItem value="sent">Terkirim</SelectItem>
                  <SelectItem value="cancelled">Dibatalkan</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-1.5">
              <Label>Tanggal Invoice *</Label>
              <Input type="date" {...register('invoice_date')} />
              {errors.invoice_date && <p className="text-xs text-red-600">{errors.invoice_date.message}</p>}
            </div>

            <div className="space-y-1.5">
              <Label>Jatuh Tempo</Label>
              <Input type="date" {...register('due_date')} />
            </div>

            <div className="space-y-1.5 sm:col-span-2">
              <Label>Catatan</Label>
              <Textarea rows={2} placeholder="Catatan tambahan..." {...register('notes')} />
            </div>
          </CardContent>
        </Card>

        {/* Items */}
        <Card>
          <CardHeader className="flex flex-row items-center justify-between">
            <CardTitle className="text-base">Item</CardTitle>
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => append({ description: '', quantity: 1, unit_price: 0, tax_percent: 0 })}
            >
              <Plus className="w-4 h-4 mr-1" />
              Tambah
            </Button>
          </CardHeader>
          <CardContent className="space-y-3">
            {fields.map((field, i) => (
              <div key={field.id} className="grid grid-cols-12 gap-2 items-end">
                <div className="col-span-12 sm:col-span-5 space-y-1">
                  {i === 0 && <Label className="text-xs">Deskripsi</Label>}
                  <Input placeholder="Nama barang/jasa" {...register(`items.${i}.description`)} />
                </div>
                <div className="col-span-4 sm:col-span-2 space-y-1">
                  {i === 0 && <Label className="text-xs">Qty</Label>}
                  <Input type="number" step="0.01" {...register(`items.${i}.quantity`, { valueAsNumber: true })} />
                </div>
                <div className="col-span-4 sm:col-span-2 space-y-1">
                  {i === 0 && <Label className="text-xs">Harga</Label>}
                  <Input type="number" step="0.01" {...register(`items.${i}.unit_price`, { valueAsNumber: true })} />
                </div>
                <div className="col-span-3 sm:col-span-2 space-y-1">
                  {i === 0 && <Label className="text-xs">PPN%</Label>}
                  <Input type="number" step="0.01" min="0" max="100" {...register(`items.${i}.tax_percent`, { valueAsNumber: true })} />
                </div>
                <div className="col-span-1">
                  {fields.length > 1 && (
                    <Button type="button" variant="ghost" size="icon" className="text-red-500" onClick={() => remove(i)}>
                      <Trash2 className="w-4 h-4" />
                    </Button>
                  )}
                </div>
              </div>
            ))}
            {errors.items && <p className="text-xs text-red-600">{errors.items.message}</p>}

            {/* Totals */}
            <div className="border-t pt-3 space-y-1 text-sm">
              <div className="flex justify-between text-gray-600">
                <span>Subtotal</span>
                <span>{formatCurrency(subtotal)}</span>
              </div>
              <div className="flex justify-between text-gray-600">
                <span>PPN</span>
                <span>{formatCurrency(taxTotal)}</span>
              </div>
              <div className="flex justify-between font-bold text-gray-900">
                <span>Total</span>
                <span>{formatCurrency(subtotal + taxTotal)}</span>
              </div>
            </div>
          </CardContent>
        </Card>

        <div className="flex gap-3">
          <Button type="submit" disabled={isSubmitting}>
            {isSubmitting && <Loader2 className="w-4 h-4 mr-2 animate-spin" />}
            {isEdit ? 'Simpan Perubahan' : 'Buat Invoice'}
          </Button>
          <Button type="button" variant="outline" onClick={() => navigate('/invoices')}>
            Batal
          </Button>
        </div>
      </form>
    </div>
  )
}
