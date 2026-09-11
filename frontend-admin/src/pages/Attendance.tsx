import { Check } from 'lucide-react'
import { useCallback, useEffect, useMemo, useState } from 'react'
import { PageHeader } from '@/components/Page'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { api } from '@/lib/api'

type SubjectType = 'student' | 'teacher'
type Row = {
  subject_id: number
  name: string
  class_name: string | null
  status: 'present' | 'absent'
  method: string | null
  check_in_at: string | null
}

const today = () => new Date().toISOString().slice(0, 10)

export default function Attendance() {
  const [type, setType] = useState<SubjectType>('student')
  const [date, setDate] = useState(today())
  const [rows, setRows] = useState<Row[]>([])
  const [loading, setLoading] = useState(true)

  const load = useCallback(() => {
    setLoading(true)
    api
      .get<{ data: Row[] }>('/attendance', { params: { date, type } })
      .then((r) => setRows(r.data.data))
      .finally(() => setLoading(false))
  }, [date, type])

  useEffect(load, [load])

  const counts = useMemo(() => {
    const present = rows.filter((r) => r.status === 'present').length
    return { present, absent: rows.length - present, total: rows.length }
  }, [rows])

  const bypass = async (r: Row) => {
    const reason = prompt(`Mark ${r.name} present — reason for bypass (e.g. face scan failed):`)
    if (!reason?.trim()) return
    await api.post('/attendance/bypass', { subject_type: type, subject_id: r.subject_id, reason: reason.trim() })
    load()
  }

  const isToday = date === today()

  return (
    <>
      <PageHeader
        title="Attendance"
        subtitle="Roster by date. No check-in = absent. Failed scans can be bypassed (audited)."
      />

      <div className="mb-4 flex flex-wrap items-center gap-3">
        <div className="inline-flex overflow-hidden rounded-md border border-border">
          {(['student', 'teacher'] as SubjectType[]).map((t) => (
            <button
              key={t}
              onClick={() => setType(t)}
              className={`cursor-pointer px-3 py-1.5 text-sm capitalize transition-colors ${
                type === t ? 'bg-primary text-primary-foreground' : 'bg-card hover:bg-muted'
              }`}
            >
              {t}s
            </button>
          ))}
        </div>
        <Input type="date" value={date} max={today()} onChange={(e) => setDate(e.target.value)} className="w-auto" />
        <div className="ml-auto flex gap-2 text-sm">
          <Badge variant="present">Present {counts.present}</Badge>
          <Badge variant="absent">Absent {counts.absent}</Badge>
          <Badge variant="neutral">Total {counts.total}</Badge>
        </div>
      </div>

      <Card className="overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="border-b border-border bg-muted/40 text-left text-xs text-muted-foreground">
              <tr>
                <th className="px-4 py-2 font-medium">Name</th>
                {type === 'student' && <th className="px-4 py-2 font-medium">Class</th>}
                <th className="px-4 py-2 font-medium">Status</th>
                <th className="px-4 py-2 font-medium">Check-in</th>
                <th className="px-4 py-2 font-medium text-right">Action</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <td colSpan={5} className="px-4 py-8 text-center text-muted-foreground">
                    Loading…
                  </td>
                </tr>
              ) : rows.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-4 py-8 text-center text-muted-foreground">
                    No {type}s found.
                  </td>
                </tr>
              ) : (
                rows.map((r) => (
                  <tr key={r.subject_id} className="border-b border-border transition-colors hover:bg-muted/40">
                    <td className="px-4 py-2 font-medium">{r.name}</td>
                    {type === 'student' && <td className="px-4 py-2 text-muted-foreground">{r.class_name ?? '—'}</td>}
                    <td className="px-4 py-2">
                      {r.status === 'present' ? (
                        <span className="flex items-center gap-1.5">
                          <Badge variant="present">Present</Badge>
                          {r.method === 'bypass' && <Badge variant="bypass">bypass</Badge>}
                        </span>
                      ) : (
                        <Badge variant="absent">Absent</Badge>
                      )}
                    </td>
                    <td className="tabular px-4 py-2 text-muted-foreground">
                      {r.check_in_at ? new Date(r.check_in_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '—'}
                    </td>
                    <td className="px-4 py-2 text-right">
                      {r.status === 'absent' && isToday && (
                        <Button variant="outline" size="sm" onClick={() => bypass(r)}>
                          <Check />
                          Mark present
                        </Button>
                      )}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </Card>
    </>
  )
}
