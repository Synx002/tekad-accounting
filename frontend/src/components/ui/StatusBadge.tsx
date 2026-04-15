import { Badge } from './badge'

const statusConfig: Record<string, { label: string; variant: 'default' | 'secondary' | 'success' | 'warning' | 'destructive' | 'outline' }> = {
  draft: { label: 'Draf', variant: 'secondary' },
  sent: { label: 'Terkirim', variant: 'default' },
  paid: { label: 'Lunas', variant: 'success' },
  received: { label: 'Diterima', variant: 'default' },
  cancelled: { label: 'Dibatalkan', variant: 'destructive' },
  active: { label: 'Aktif', variant: 'success' },
  fully_depreciated: { label: 'Habis', variant: 'secondary' },
  disposed: { label: 'Dilepas', variant: 'destructive' },
  posted: { label: 'Diposting', variant: 'success' },
}

export default function StatusBadge({ status }: { status: string }) {
  const config = statusConfig[status] ?? { label: status, variant: 'outline' as const }
  return <Badge variant={config.variant}>{config.label}</Badge>
}
