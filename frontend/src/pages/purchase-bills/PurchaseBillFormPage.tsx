import { useEffect } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useForm, useFieldArray } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Trash2, Loader2 } from 'lucide-react'
import { purchaseBillsApi } from '@/api/purchaseBills'
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
  quantity: z.number().gt(0),
  unit_price: z.number().gte(0),
  tax_percent: z.number().min(0).max(100),
})

const schema = z.object({
  purchase_date: z.string().min(1),
  due_date: z.string().optional(),
  supplier_name: z.string().min(1),
  status: z.enum(['draft', 'received', 'paid', 'cancelled']),
  notes: z.string().optional(),
  items: z.array(itemSchema).min(1),
})

type FormData = z.infer<typeof schema>

export default function PurchaseBillFormPage() {
  const { id } = useParams()
  const isEdit = !!id
  const navigate = useNavigate()
  const qc = useQueryClient()

  const { data: existing } = useQuery({
    queryKey: ['purchase-bill', id],
    queryFn: () => purchaseBillsApi.show(Number(id)).then((r) => r.data.data),
    enabled: isEdit,
  })

  const { register, handleSubmit, control, watch, setValue, reset, formState: { errors, isSubmitting } } =
    useForm<FormData>({
      resolver: zodResolver(schema),
      defaultValues: { status: 'draft', items: [{ description: '', quantity: 1, unit_price: 0, tax_percent: 0 }] },
    })

  const { fields, append, remove } = useFieldArray({ control, name: 'items' })

  useEffect(() => {
    if (existing) reset({
      purchase_date: existing.purchase_date,
      due_date: existing.due_date ?? '',
      supplier_name: existing.supplier_name,
      status: existing.status,
      notes: existing.notes ?? '',
      items: existing.items.map((it) => ({ description: it.description, quantity: it.quantity, unit_price: Number(it.unit_price), tax_percent: it.tax_percent })),
    })
  }, [existing, reset])

  const createMutation = useMutation({
    mutationFn: (d: FormData) => purchaseBillsApi.create(d),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['purchase-bills'] }); navigate('/purchase-bills') },
  })
  const updateMutation = useMutation({
    mutationFn: (d: FormData) => purchaseBillsApi.update(Number(id), d),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['purchase-bills'] }); navigate('/purchase-bills') },
  })

  const onSubmit = (d: FormData) => isEdit ? updateMutation.mutate(d) : createMutation.mutate(d)

  const items = watch('items')
  const subtotal = items.reduce((s, it) => s + it.quantity * it.unit_price, 0)
  const taxTotal = items.reduce((s, it) => s + it.quantity * it.unit_price * (it.tax_percent / 100), 0)

  return (
    <div>
      <PageHeader title={isEdit ? 'Edit Purchase Bill' : 'Buat Purchase Bill'} />
      <form onSubmit={handleSubmit(onSubmit)} className="space-y-6 max-w-3xl">
        <Card>
          <CardHeader><CardTitle className="text-base">Informasi Pembelian</CardTitle></CardHeader>
          <CardContent className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label>Supplier *</Label>
              <Input placeholder="Nama supplier" {...register('supplier_name')} />
              {errors.supplier_name && <p className="text-xs text-red-600">{errors.supplier_name.message}</p>}
            </div>
            <div className="space-y-1.5">
              <Label>Status</Label>
              <Select defaultValue="draft" onValueChange={(v) => setValue('status', v as FormData['status'])} value={watch('status')}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="draft">Draf</SelectItem>
                  <SelectItem value="received">Diterima</SelectItem>
                  <SelectItem value="cancelled">Dibatalkan</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label>Tanggal *</Label>
              <Input type="date" {...register('purchase_date')} />
            </div>
            <div className="space-y-1.5">
              <Label>Jatuh Tempo</Label>
              <Input type="date" {...register('due_date')} />
            </div>
            <div className="space-y-1.5 sm:col-span-2">
              <Label>Catatan</Label>
              <Textarea rows={2} {...register('notes')} />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between">
            <CardTitle className="text-base">Item</CardTitle>
            <Button type="button" variant="outline" size="sm" onClick={() => append({ description: '', quantity: 1, unit_price: 0, tax_percent: 0 })}>
              <Plus className="w-4 h-4 mr-1" />Tambah
            </Button>
          </CardHeader>
          <CardContent className="space-y-3">
            {fields.map((field, i) => (
              <div key={field.id} className="grid grid-cols-12 gap-2 items-end">
                <div className="col-span-12 sm:col-span-5 space-y-1">
                  {i === 0 && <Label className="text-xs">Deskripsi</Label>}
                  <Input {...register(`items.${i}.description`)} />
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
            <div className="border-t pt-3 space-y-1 text-sm">
              <div className="flex justify-between text-gray-600"><span>Subtotal</span><span>{formatCurrency(subtotal)}</span></div>
              <div className="flex justify-between text-gray-600"><span>PPN</span><span>{formatCurrency(taxTotal)}</span></div>
              <div className="flex justify-between font-bold text-gray-900"><span>Total</span><span>{formatCurrency(subtotal + taxTotal)}</span></div>
            </div>
          </CardContent>
        </Card>

        <div className="flex gap-3">
          <Button type="submit" disabled={isSubmitting}>
            {isSubmitting && <Loader2 className="w-4 h-4 mr-2 animate-spin" />}
            {isEdit ? 'Simpan' : 'Buat Bill'}
          </Button>
          <Button type="button" variant="outline" onClick={() => navigate('/purchase-bills')}>Batal</Button>
        </div>
      </form>
    </div>
  )
}
