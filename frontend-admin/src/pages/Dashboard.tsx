import { CalendarCheck, ScanFace, UserX, Users } from 'lucide-react'
import { useEffect, useState } from 'react'
import { PageHeader } from '@/components/Page'
import { Card, CardContent } from '@/components/ui/card'
import { api } from '@/lib/api'
import { useAuth } from '@/lib/auth'

type Stats = {
  total_students: number
  present_today: number
  absent_today: number
  pending_enrolment: number
}

type Tile = { key: keyof Stats; label: string; icon: typeof Users; tint: string }
const TILES: Tile[] = [
  { key: 'present_today', label: 'Present today', icon: CalendarCheck, tint: 'text-success' },
  { key: 'absent_today', label: 'Absent today', icon: UserX, tint: 'text-destructive' },
  { key: 'pending_enrolment', label: 'Pending enrolment', icon: ScanFace, tint: 'text-accent' },
  { key: 'total_students', label: 'Total students', icon: Users, tint: 'text-primary' },
]

export default function Dashboard() {
  const { user } = useAuth()
  const [stats, setStats] = useState<Stats | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    api
      .get<Stats>('/dashboard/stats')
      .then((r) => setStats(r.data))
      .finally(() => setLoading(false))
  }, [])

  return (
    <>
      <PageHeader title={`Welcome, ${user?.name ?? ''}`} subtitle="Today at a glance" />
      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        {TILES.map(({ key, label, icon: Icon, tint }) => (
          <Card key={key}>
            <CardContent className="flex items-center justify-between p-4">
              <div>
                <p className="text-xs text-muted-foreground">{label}</p>
                <p className="tabular mt-1 text-2xl font-semibold">
                  {loading || !stats ? '—' : stats[key]}
                </p>
              </div>
              <Icon className={tint} />
            </CardContent>
          </Card>
        ))}
      </div>
    </>
  )
}
