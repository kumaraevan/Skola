import { PageHeader, Placeholder } from '@/components/Page'
import { Button } from '@/components/ui/button'

export default function Students() {
  return (
    <>
      <PageHeader
        title="Students"
        subtitle="Roster, class assignment, and enrolment status"
        actions={<Button>Add student</Button>}
      />
      <Placeholder endpoint="GET /api/students (TODO)" note="DataTable with sort/filter/pagination; enrolment-status badge per row." />
    </>
  )
}
