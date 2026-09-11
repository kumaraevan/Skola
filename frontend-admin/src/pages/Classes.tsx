import { AxiosError } from 'axios'
import { Pencil, Plus, Trash2 } from 'lucide-react'
import { useEffect, useMemo, useRef, useState } from 'react'
import { PageHeader } from '@/components/Page'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Field, Select } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { api } from '@/lib/api'

type TeacherOption = { id: number; user: { id: number; name: string } }
type SchoolClass = {
  id: number
  name: string
  grade: string | null
  homeroom_teacher_id: number | null
  students_count: number
  homeroom_teacher?: { id: number; user: { id: number; name: string } } | null
}

type FormState = { name: string; grade: string; homeroom_teacher_id: string }
const EMPTY: FormState = { name: '', grade: '', homeroom_teacher_id: '' }

export default function Classes() {
  const [classes, setClasses] = useState<SchoolClass[]>([])
  const [teachers, setTeachers] = useState<TeacherOption[]>([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [editing, setEditing] = useState<SchoolClass | null>(null)
  const [form, setForm] = useState<FormState>(EMPTY)
  const [formError, setFormError] = useState('')
  const [saving, setSaving] = useState(false)
  const dialogRef = useRef<HTMLDialogElement>(null)

  const load = () => {
    setLoading(true)
    api
      .get<{ data: SchoolClass[] }>('/classes')
      .then((r) => setClasses(r.data.data))
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    load()
    api.get<{ data: TeacherOption[] }>('/teachers').then((r) => setTeachers(r.data.data))
  }, [])

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase()
    if (!q) return classes
    return classes.filter((c) => c.name.toLowerCase().includes(q) || (c.grade ?? '').toLowerCase().includes(q))
  }, [classes, search])

  const openNew = () => {
    setEditing(null)
    setForm(EMPTY)
    setFormError('')
    dialogRef.current?.showModal()
  }

  const openEdit = (c: SchoolClass) => {
    setEditing(c)
    setForm({
      name: c.name,
      grade: c.grade ?? '',
      homeroom_teacher_id: c.homeroom_teacher_id ? String(c.homeroom_teacher_id) : '',
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
      grade: form.grade || null,
      homeroom_teacher_id: form.homeroom_teacher_id ? Number(form.homeroom_teacher_id) : null,
    }
    try {
      if (editing) await api.put(`/classes/${editing.id}`, payload)
      else await api.post('/classes', payload)
      dialogRef.current?.close()
      load()
    } catch (err) {
      const ax = err as AxiosError<{ message?: string }>
      setFormError(ax.response?.data?.message ?? 'Save failed.')
    } finally {
      setSaving(false)
    }
  }

  const remove = async (c: SchoolClass) => {
    const warn =
      c.students_count > 0
        ? `Delete ${c.name}? ${c.students_count} student(s) will be left without a class.`
        : `Delete ${c.name}?`
    if (!confirm(warn)) return
    await api.delete(`/classes/${c.id}`)
    load()
  }

  return (
    <>
      <PageHeader
        title="Classes"
        subtitle="Class list and homeroom teacher assignment"
        actions={
          <Button onClick={openNew}>
            <Plus />
            Add class
          </Button>
        }
      />

      <div className="mb-3 max-w-xs">
        <Input placeholder="Search name or grade…" value={search} onChange={(e) => setSearch(e.target.value)} />
      </div>

      <Card className="overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="border-b border-border bg-muted/40 text-left text-xs text-muted-foreground">
              <tr>
                <th className="px-4 py-2 font-medium">Name</th>
                <th className="px-4 py-2 font-medium">Grade</th>
                <th className="px-4 py-2 font-medium">Homeroom teacher</th>
                <th className="px-4 py-2 font-medium text-right">Students</th>
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
                    No classes found.
                  </td>
                </tr>
              ) : (
                filtered.map((c) => (
                  <tr key={c.id} className="border-b border-border transition-colors hover:bg-muted/40">
                    <td className="px-4 py-2 font-medium">{c.name}</td>
                    <td className="px-4 py-2 text-muted-foreground">{c.grade ?? '—'}</td>
                    <td className="px-4 py-2">{c.homeroom_teacher?.user.name ?? '—'}</td>
                    <td className="tabular px-4 py-2 text-right text-muted-foreground">{c.students_count}</td>
                    <td className="px-4 py-2">
                      <div className="flex justify-end gap-1">
                        <Button variant="ghost" size="icon" onClick={() => openEdit(c)} aria-label={`Edit ${c.name}`}>
                          <Pencil />
                        </Button>
                        <Button
                          variant="ghost"
                          size="icon"
                          onClick={() => remove(c)}
                          aria-label={`Delete ${c.name}`}
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
          <h2 className="mb-4 text-base font-semibold">{editing ? 'Edit class' : 'Add class'}</h2>
          <div className="space-y-3">
            <Field label="Name">
              <Input required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
            </Field>
            <Field label="Grade">
              <Input value={form.grade} onChange={(e) => setForm({ ...form, grade: e.target.value })} />
            </Field>
            <Field label="Homeroom teacher">
              <Select
                value={form.homeroom_teacher_id}
                onChange={(e) => setForm({ ...form, homeroom_teacher_id: e.target.value })}
              >
                <option value="">— none —</option>
                {teachers.map((t) => (
                  <option key={t.id} value={t.id}>
                    {t.user.name}
                  </option>
                ))}
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
