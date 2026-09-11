import type { ReactNode } from 'react'
import { Card, CardContent } from '@/components/ui/card'

export function PageHeader({ title, subtitle, actions }: { title: string; subtitle?: string; actions?: ReactNode }) {
  return (
    <div className="mb-6 flex items-start justify-between gap-4">
      <div>
        <h1 className="text-xl font-semibold tracking-tight">{title}</h1>
        {subtitle && <p className="mt-0.5 text-sm text-muted-foreground">{subtitle}</p>}
      </div>
      {actions}
    </div>
  )
}

/** Empty-state placeholder for modules whose UI is not built yet. Names the API it will use. */
export function Placeholder({ endpoint, note }: { endpoint: string; note?: string }) {
  return (
    <Card>
      <CardContent className="flex flex-col items-center gap-2 py-12 text-center">
        <p className="text-sm text-muted-foreground">No UI yet — this page is scaffolded.</p>
        <code className="tabular rounded bg-muted px-2 py-1 text-xs text-foreground">{endpoint}</code>
        {note && <p className="max-w-md text-xs text-muted-foreground">{note}</p>}
      </CardContent>
    </Card>
  )
}
