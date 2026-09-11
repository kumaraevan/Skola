import { PageHeader, Placeholder } from '@/components/Page'

export default function Enrolment() {
  return (
    <>
      <PageHeader
        title="Face Enrolment"
        subtitle="Consent must be granted before the initial face scan (PLANNING §4.6)"
      />
      <Placeholder
        endpoint="POST /api/enrolment/consent → POST /api/enrolment/face"
        note="Flow: pick subject → confirm biometric consent → capture/generate embedding on-device → store. Re-enrolment deactivates prior embeddings."
      />
    </>
  )
}
