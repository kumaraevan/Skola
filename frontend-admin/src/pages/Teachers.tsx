import { PageHeader, Placeholder } from '@/components/Page'
import { Button } from '@/components/ui/button'

export default function Teachers() {
  return (
    <>
      <PageHeader
        title="Teachers"
        subtitle="Staff roster and enrolment status"
        actions={<Button>Add teacher</Button>}
      />
      <Placeholder endpoint="GET /api/teachers (TODO)" note="DataTable; a teacher is also an attendee (own attendance feeds payroll later)." />
    </>
  )
}
