import { Check, Info, ScanFace } from 'lucide-react'
import { useCallback, useEffect, useState } from 'react'
import { PageHeader } from '@/components/Page'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { api } from '@/lib/api'

type SubjectType = 'student' | 'teacher'
type EnrolmentStatus = 'pending_enrolment' | 'enrolled' | 'inactive'
type Row = { subject_id: number; name: string; enrolment_status: EnrolmentStatus; has_consent: boolean }

const STATUS_LABEL: Record<EnrolmentStatus, string> = {
  pending_enrolment: 'Pending',
  enrolled: 'Enrolled',
  inactive: 'Inactive',
}
function statusBadge(s: EnrolmentStatus) {
  return s === 'enrolled' ? 'present' : s === 'inactive' ? 'neutral' : 'pending'
}

export default function Enrolment() {
  const [type, setType] = useState<SubjectType>('student')
  const [rows, setRows] = useState<Row[]>([])
  const [loading, setLoading] = useState(true)

  const load = useCallback(() => {
    setLoading(true)
    api
      .get<{ data: Row[] }>('/enrolment', { params: { type } })
      .then((r) => setRows(r.data.data))
      .finally(() => setLoading(false))
  }, [type])

  useEffect(load, [load])

  const grantConsent = async (r: Row) => {
    if (!confirm(`Record biometric consent for ${r.name}? Consent must be given by the parent/guardian.`)) return
    await api.post('/enrolment/consent', { subject_type: type, subject_id: r.subject_id })
    load()
  }

  return (
    <>
      <PageHeader
        title="Face Enrolment"
        subtitle="Consent must be granted before the initial face scan"
      />

      <div className="mb-4 flex items-start gap-2 rounded-md border border-border bg-muted/40 px-3 py-2 text-xs text-muted-foreground">
        <Info className="mt-0.5 size-4 shrink-0 text-primary" />
        <p>
          The actual face scan happens on the gate kiosk (camera + on-device model). This page records{' '}
          <strong>biometric consent</strong> and tracks who is enrolled. A subject can be scanned once consent is granted.
        </p>
      </div>

      <div className="mb-4 inline-flex overflow-hidden rounded-md border border-border">
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

      <Card className="overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="border-b border-border bg-muted/40 text-left text-xs text-muted-foreground">
              <tr>
                <th className="px-4 py-2 font-medium">Name</th>
                <th className="px-4 py-2 font-medium">Consent</th>
                <th className="px-4 py-2 font-medium">Enrolment</th>
                <th className="px-4 py-2 font-medium text-right">Action</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <td colSpan={4} className="px-4 py-8 text-center text-muted-foreground">
                    Loading…
                  </td>
                </tr>
              ) : rows.length === 0 ? (
                <tr>
                  <td colSpan={4} className="px-4 py-8 text-center text-muted-foreground">
                    No {type}s found.
                  </td>
                </tr>
              ) : (
                rows.map((r) => (
                  <tr key={r.subject_id} className="border-b border-border transition-colors hover:bg-muted/40">
                    <td className="px-4 py-2 font-medium">{r.name}</td>
                    <td className="px-4 py-2">
                      {r.has_consent ? (
                        <Badge variant="present">Granted</Badge>
                      ) : (
                        <Badge variant="neutral">Not given</Badge>
                      )}
                    </td>
                    <td className="px-4 py-2">
                      <span className="flex items-center gap-2">
                        <Badge variant={statusBadge(r.enrolment_status)}>{STATUS_LABEL[r.enrolment_status]}</Badge>
                        {r.enrolment_status === 'pending_enrolment' && r.has_consent && (
                          <span className="flex items-center gap-1 text-xs text-muted-foreground">
                            <ScanFace className="size-3.5" />
                            awaiting kiosk scan
                          </span>
                        )}
                      </span>
                    </td>
                    <td className="px-4 py-2 text-right">
                      {!r.has_consent && (
                        <Button variant="outline" size="sm" onClick={() => grantConsent(r)}>
                          <Check />
                          Grant consent
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
