import { AxiosError } from 'axios'
import { Pencil, Plus, Trash2 } from 'lucide-react'
import { useEffect, useMemo, useRef, useState } from 'react'
import { PageHeader } from '@/components/Page'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { api } from '@/lib/api'

type EnrolmentStatus = 'pending_enrolment' | 'enrolled' | 'inactive'
type SchoolClass = { id: number; name: string }
type Student = {
  id: number
  name: string
  nis: string | null
  class_id: number | null
  enrolment_status: EnrolmentStatus
  status: string
  school_class?: SchoolClass | null
}

type FormState = { name: string; nis: string; class_id: string; enrolment_status: EnrolmentStatus }
const EMPTY: FormState = { name: '', nis: '', class_id: '', enrolment_status: 'pending_enrolment' }

const ENROLMENT_LABEL: Record<EnrolmentStatus, string> = {
  pending_enrolment: 'Pending',
  enrolled: 'Enrolled',
  inactive: 'Inactive',
}
function enrolmentBadge(s: EnrolmentStatus) {
  return s === 'enrolled' ? 'present' : s === 'inactive' ? 'neutral' : 'pending'
}

export default function Students() {
  const [students, setStudents] = useState<Student[]>([])
  const [classes, setClasses] = useState<SchoolClass[]>([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [editing, setEditing] = useState<Student | null>(null)
  const [form, setForm] = useState<FormState>(EMPTY)
  const [formError, setFormError] = useState('')
  const [saving, setSaving] = useState(false)
  const dialogRef = useRef<HTMLDialogElement>(null)

  const load = () => {
    setLoading(true)
    api
      .get<{ data: Student[] }>('/students')
      .then((r) => setStudents(r.data.data))
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    load()
    api.get<{ data: SchoolClass[] }>('/classes').then((r) => setClasses(r.data.data))
  }, [])

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase()
    if (!q) return students
    return students.filter((s) => s.name.toLowerCase().includes(q) || (s.nis ?? '').includes(q))
  }, [students, search])

  const openNew = () => {
    setEditing(null)
    setForm(EMPTY)
    setFormError('')
    dialogRef.current?.showModal()
  }

  const openEdit = (s: Student) => {
    setEditing(s)
    setForm({
      name: s.name,
      nis: s.nis ?? '',
      class_id: s.class_id ? String(s.class_id) : '',
      enrolment_status: s.enrolment_status,
    })
    setFormError('')
    dialogRef.current?.showModal()
  }

  const submit = async (e: React.FormEvent) => {
    e.preventDefault()
    setSaving(true)
    setFormError('')
    const payload = {
      name: form.name,
      nis: form.nis || null,
      class_id: form.class_id ? Number(form.class_id) : null,
      enrolment_status: form.enrolment_status,
    }
    try {
      if (editing) await api.put(`/students/${editing.id}`, payload)
      else await api.post('/students', payload)
      dialogRef.current?.close()
      load()
    } catch (err) {
      const ax = err as AxiosError<{ message?: string }>
      setFormError(ax.response?.data?.message ?? 'Save failed.')
    } finally {
      setSaving(false)
    }
  }

  const remove = async (s: Student) => {
    if (!confirm(`Delete ${s.name}? This cannot be undone.`)) return
    await api.delete(`/students/${s.id}`)
    load()
  }

  return (
    <>
      <PageHeader
        title="Students"
        subtitle="Roster, class assignment, and enrolment status"
        actions={
          <Button onClick={openNew}>
            <Plus />
            Add student
          </Button>
        }
      />

      <div className="mb-3 max-w-xs">
        <Input placeholder="Search name or NIS…" value={search} onChange={(e) => setSearch(e.target.value)} />
      </div>

      <Card className="overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="border-b border-border bg-muted/40 text-left text-xs text-muted-foreground">
              <tr>
                <th className="px-4 py-2 font-medium">Name</th>
                <th className="px-4 py-2 font-medium">NIS</th>
                <th className="px-4 py-2 font-medium">Class</th>
                <th className="px-4 py-2 font-medium">Enrolment</th>
                <th className="px-4 py-2 font-medium text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <td colSpan={5} className="px-4 py-8 text-center text-muted-foreground">
                    Loading…
                  </td>
                </tr>
              ) : filtered.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-4 py-8 text-center text-muted-foreground">
                    No students found.
                  </td>
                </tr>
              ) : (
                filtered.map((s) => (
                  <tr key={s.id} className="border-b border-border transition-colors hover:bg-muted/40">
                    <td className="px-4 py-2 font-medium">{s.name}</td>
                    <td className="tabular px-4 py-2 text-muted-foreground">{s.nis ?? '—'}</td>
                    <td className="px-4 py-2">{s.school_class?.name ?? '—'}</td>
                    <td className="px-4 py-2">
                      <Badge variant={enrolmentBadge(s.enrolment_status)}>
                        {ENROLMENT_LABEL[s.enrolment_status]}
                      </Badge>
                    </td>
                    <td className="px-4 py-2">
                      <div className="flex justify-end gap-1">
                        <Button variant="ghost" size="icon" onClick={() => openEdit(s)} aria-label={`Edit ${s.name}`}>
                          <Pencil />
                        </Button>
                        <Button
                          variant="ghost"
                          size="icon"
                          onClick={() => remove(s)}
                          aria-label={`Delete ${s.name}`}
                          className="text-destructive hover:bg-destructive/10"
                        >
                          <Trash2 />
                        </Button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </Card>

      <dialog
        ref={dialogRef}
        className="m-auto w-full max-w-md rounded-lg border border-border bg-card p-0 text-card-foreground backdrop:bg-black/40"
      >
        <form onSubmit={submit} className="p-5">
          <h2 className="mb-4 text-base font-semibold">{editing ? 'Edit student' : 'Add student'}</h2>
          <div className="space-y-3">
            <Field label="Name">
              <Input required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
            </Field>
            <Field label="NIS">
              <Input value={form.nis} onChange={(e) => setForm({ ...form, nis: e.target.value })} />
            </Field>
            <Field label="Class">
              <Select value={form.class_id} onChange={(e) => setForm({ ...form, class_id: e.target.value })}>
                <option value="">— none —</option>
                {classes.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name}
                  </option>
                ))}
              </Select>
            </Field>
            <Field label="Enrolment status">
              <Select
                value={form.enrolment_status}
                onChange={(e) => setForm({ ...form, enrolment_status: e.target.value as EnrolmentStatus })}
              >
                <option value="pending_enrolment">Pending</option>
                <option value="enrolled">Enrolled</option>
                <option value="inactive">Inactive</option>
              </Select>
            </Field>
            {formError && (
              <p className="rounded-md bg-destructive/10 px-3 py-2 text-xs text-destructive" role="alert">
                {formError}
              </p>
            )}
          </div>
          <div className="mt-5 flex justify-end gap-2">
            <Button type="button" variant="outline" onClick={() => dialogRef.current?.close()}>
              Cancel
            </Button>
            <Button type="submit" disabled={saving}>
              {saving ? 'Saving…' : 'Save'}
            </Button>
          </div>
        </form>
      </dialog>
    </>
  )
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <label className="block space-y-1">
      <span className="text-xs font-medium">{label}</span>
      {children}
    </label>
  )
}

function Select({ className, ...props }: React.SelectHTMLAttributes<HTMLSelectElement>) {
  return (
    <select
      className="flex h-9 w-full rounded-md border border-input bg-card px-3 text-sm text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
      {...props}
    />
  )
}
