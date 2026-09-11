import { CalendarCheck, ScanFace, UserX, Users } from 'lucide-react'
import { PageHeader } from '@/components/Page'
import { Card, CardContent } from '@/components/ui/card'
import { useAuth } from '@/lib/auth'

type Stat = { label: string; value: string; icon: typeof Users; tint: string }

// Values are placeholders until the reporting endpoints land (PLANNING §7).
const STATS: Stat[] = [
  { label: 'Present today', value: '—', icon: CalendarCheck, tint: 'text-success' },
  { label: 'Absent today', value: '—', icon: UserX, tint: 'text-destructive' },
  { label: 'Pending enrolment', value: '—', icon: ScanFace, tint: 'text-accent' },
  { label: 'Total students', value: '—', icon: Users, tint: 'text-primary' },
]

export default function Dashboard() {
  const { user } = useAuth()
  return (
    <>
      <PageHeader title={`Welcome, ${user?.name ?? ''}`} subtitle="Today at a glance" />
      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        {STATS.map(({ label, value, icon: Icon, tint }) => (
          <Card key={label}>
            <CardContent className="flex items-center justify-between p-4">
              <div>
                <p className="text-xs text-muted-foreground">{label}</p>
                <p className="tabular mt-1 text-2xl font-semibold">{value}</p>
              </div>
              <Icon className={tint} />
            </CardContent>
          </Card>
        ))}
      </div>
      <p className="mt-6 text-xs text-muted-foreground">
        Metrics are placeholders — wire to the reporting endpoints when built.
      </p>
    </>
  )
}
