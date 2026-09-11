import { AxiosError } from 'axios'
import { Pencil, Plus, Trash2 } from 'lucide-react'
import { useEffect, useMemo, useRef, useState } from 'react'
import { PageHeader } from '@/components/Page'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Field, Select } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { api } from '@/lib/api'

type EnrolmentStatus = 'pending_enrolment' | 'enrolled' | 'inactive'
type Teacher = {
  id: number
  nip: string | null
  enrolment_status: EnrolmentStatus
  user: { id: number; name: string; email: string }
}

type FormState = { name: string; email: string; password: string; nip: string; enrolment_status: EnrolmentStatus }
const EMPTY: FormState = { name: '', email: '', password: '', nip: '', enrolment_status: 'pending_enrolment' }

const ENROLMENT_LABEL: Record<EnrolmentStatus, string> = {
  pending_enrolment: 'Pending',
  enrolled: 'Enrolled',
  inactive: 'Inactive',
}
function enrolmentBadge(s: EnrolmentStatus) {
  return s === 'enrolled' ? 'present' : s === 'inactive' ? 'neutral' : 'pending'
}

export default function Teachers() {
  const [teachers, setTeachers] = useState<Teacher[]>([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [editing, setEditing] = useState<Teacher | null>(null)
  const [form, setForm] = useState<FormState>(EMPTY)
  const [formError, setFormError] = useState('')
  const [saving, setSaving] = useState(false)
  const dialogRef = useRef<HTMLDialogElement>(null)

  const load = () => {
    setLoading(true)
    api
      .get<{ data: Teacher[] }>('/teachers')
      .then((r) => setTeachers(r.data.data))
      .finally(() => setLoading(false))
  }

  useEffect(load, [])

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase()
    if (!q) return teachers
    return teachers.filter(
      (t) => t.user.name.toLowerCase().includes(q) || t.user.email.toLowerCase().includes(q) || (t.nip ?? '').includes(q),
    )
  }, [teachers, search])

  const openNew = () => {
    setEditing(null)
    setForm(EMPTY)
    setFormError('')
    dialogRef.current?.showModal()
  }

  const openEdit = (t: Teacher) => {
    setEditing(t)
    setForm({ name: t.user.name, email: t.user.email, password: '', nip: t.nip ?? '', enrolment_status: t.enrolment_status })
    setFormError('')
    dialogRef.current?.showModal()
  }

  const submit = async (e: React.FormEvent) => {
    e.preventDefault()
    setSaving(true)
    setFormError('')
    try {
      if (editing) {
        await api.put(`/teachers/${editing.id}`, {
          name: form.name,
          email: form.email,
          nip: form.nip || null,
          enrolment_status: form.enrolment_status,
        })
      } else {
        await api.post('/teachers', {
          name: form.name,
          email: form.email,
          password: form.password,
          nip: form.nip || null,
        })
      }
      dialogRef.current?.close()
      load()
    } catch (err) {
      const ax = err as AxiosError<{ message?: string }>
      setFormError(ax.response?.data?.message ?? 'Save failed.')
    } finally {
      setSaving(false)
    }
  }

  const remove = async (t: Teacher) => {
    if (!confirm(`Delete ${t.user.name} and their login? This cannot be undone.`)) return
    await api.delete(`/teachers/${t.id}`)
    load()
  }

  return (
    <>
      <PageHeader
        title="Teachers"
        subtitle="Staff roster and login accounts. A teacher is also an attendee."
        actions={
          <Button onClick={openNew}>
            <Plus />
            Add teacher
          </Button>
        }
      />

      <div className="mb-3 max-w-xs">
        <Input placeholder="Search name, email, or NIP…" value={search} onChange={(e) => setSearch(e.target.value)} />
      </div>

      <Card className="overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="border-b border-border bg-muted/40 text-left text-xs text-muted-foreground">
              <tr>
                <th className="px-4 py-2 font-medium">Name</th>
                <th className="px-4 py-2 font-medium">Email</th>
                <th className="px-4 py-2 font-medium">NIP</th>
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
                    No teachers found.
                  </td>
                </tr>
              ) : (
                filtered.map((t) => (
                  <tr key={t.id} className="border-b border-border transition-colors hover:bg-muted/40">
                    <td className="px-4 py-2 font-medium">{t.user.name}</td>
                    <td className="px-4 py-2 text-muted-foreground">{t.user.email}</td>
                    <td className="tabular px-4 py-2 text-muted-foreground">{t.nip ?? '—'}</td>
                    <td className="px-4 py-2">
                      <Badge variant={enrolmentBadge(t.enrolment_status)}>{ENROLMENT_LABEL[t.enrolment_status]}</Badge>
                    </td>
                    <td className="px-4 py-2">
                      <div className="flex justify-end gap-1">
                        <Button variant="ghost" size="icon" onClick={() => openEdit(t)} aria-label={`Edit ${t.user.name}`}>
                          <Pencil />
                        </Button>
                        <Button
                          variant="ghost"
                          size="icon"
                          onClick={() => remove(t)}
                          aria-label={`Delete ${t.user.name}`}
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
          <h2 className="mb-4 text-base font-semibold">{editing ? 'Edit teacher' : 'Add teacher'}</h2>
          <div className="space-y-3">
            <Field label="Name">
              <Input required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
            </Field>
            <Field label="Email">
              <Input
                type="email"
                required
                value={form.email}
                onChange={(e) => setForm({ ...form, email: e.target.value })}
              />
            </Field>
            {!editing && (
              <Field label="Password">
                <Input
                  type="password"
                  required
                  minLength={8}
                  value={form.password}
                  onChange={(e) => setForm({ ...form, password: e.target.value })}
                />
              </Field>
            )}
            <Field label="NIP">
              <Input value={form.nip} onChange={(e) => setForm({ ...form, nip: e.target.value })} />
            </Field>
            {editing && (
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
            )}
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
