import { PageHeader, Placeholder } from '@/components/Page'
import { Button } from '@/components/ui/button'

export default function Classes() {
  return (
    <>
      <PageHeader
        title="Classes"
        subtitle="Class list and homeroom teacher assignment"
        actions={<Button>Add class</Button>}
      />
      <Placeholder endpoint="GET /api/classes (TODO)" note="DataTable; assign homeroom teacher per class." />
    </>
  )
}
