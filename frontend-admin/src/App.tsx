import type { ReactNode } from 'react'
import { Navigate, Route, Routes } from 'react-router-dom'
import Layout from '@/components/Layout'
import { useAuth } from '@/lib/auth'
import Attendance from '@/pages/Attendance'
import Classes from '@/pages/Classes'
import Dashboard from '@/pages/Dashboard'
import Enrolment from '@/pages/Enrolment'
import Login from '@/pages/Login'
import Students from '@/pages/Students'
import Teachers from '@/pages/Teachers'

function Protected({ children }: { children: ReactNode }) {
  const { user, loading } = useAuth()
  if (loading) return <div className="grid min-h-screen place-items-center text-sm text-muted-foreground">Loading…</div>
  if (!user) return <Navigate to="/login" replace />
  return <>{children}</>
}

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route
        element={
          <Protected>
            <Layout />
          </Protected>
        }
      >
        <Route index element={<Dashboard />} />
        <Route path="students" element={<Students />} />
        <Route path="classes" element={<Classes />} />
        <Route path="teachers" element={<Teachers />} />
        <Route path="attendance" element={<Attendance />} />
        <Route path="enrolment" element={<Enrolment />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
