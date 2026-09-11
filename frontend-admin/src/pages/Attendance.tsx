import { PageHeader, Placeholder } from '@/components/Page'
import { Badge } from '@/components/ui/badge'

export default function Attendance() {
  return (
    <>
      <PageHeader
        title="Attendance"
        subtitle="Daily records by date. Failed scans can be bypassed by gate staff (audited)."
        actions={
          <div className="flex gap-2">
            <Badge variant="present">present</Badge>
            <Badge variant="absent">absent</Badge>
            <Badge variant="bypass">bypass</Badge>
          </div>
        }
      />
      <Placeholder
        endpoint="POST /api/attendance/bypass · GET /api/attendance (TODO)"
        note="DataTable by date; bypass opens a Dialog requiring a reason (records method=bypass + bypassed_by)."
      />
    </>
  )
}
