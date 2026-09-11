import {
  CalendarCheck,
  GraduationCap,
  LayoutDashboard,
  LogOut,
  Moon,
  ScanFace,
  School,
  Sun,
  Users,
} from 'lucide-react'
import { useEffect, useState } from 'react'
import { NavLink, Outlet } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { useAuth, type Role } from '@/lib/auth'
import { cn } from '@/lib/utils'

type NavItem = { to: string; label: string; icon: typeof Users; roles: Role[] }

const NAV: NavItem[] = [
  { to: '/', label: 'Dashboard', icon: LayoutDashboard, roles: ['super_admin', 'school_admin', 'teacher'] },
  { to: '/students', label: 'Students', icon: Users, roles: ['super_admin', 'school_admin'] },
  { to: '/classes', label: 'Classes', icon: School, roles: ['super_admin', 'school_admin'] },
  { to: '/teachers', label: 'Teachers', icon: GraduationCap, roles: ['super_admin', 'school_admin'] },
  { to: '/attendance', label: 'Attendance', icon: CalendarCheck, roles: ['super_admin', 'school_admin', 'teacher'] },
  { to: '/enrolment', label: 'Enrolment', icon: ScanFace, roles: ['super_admin', 'school_admin'] },
]

function useDarkMode() {
  const [dark, setDark] = useState(() => localStorage.getItem('skola_theme') === 'dark')
  useEffect(() => {
    document.documentElement.classList.toggle('dark', dark)
    localStorage.setItem('skola_theme', dark ? 'dark' : 'light')
  }, [dark])
  return [dark, () => setDark((d) => !d)] as const
}

export default function Layout() {
  const { user, logout } = useAuth()
  const [dark, toggleDark] = useDarkMode()
  const items = NAV.filter((i) => user && i.roles.includes(user.role))

  return (
    <div className="grid min-h-screen grid-cols-[220px_1fr]">
      <aside className="flex flex-col border-r border-border bg-card">
        <div className="flex h-14 items-center gap-2 border-b border-border px-4">
          <School className="text-primary" />
          <span className="font-semibold tracking-tight">Skola</span>
        </div>
        <nav className="flex-1 space-y-1 p-2">
          {items.map(({ to, label, icon: Icon }) => (
            <NavLink
              key={to}
              to={to}
              end={to === '/'}
              className={({ isActive }) =>
                cn(
                  'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                  isActive ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted',
                )
              }
            >
              <Icon />
              {label}
            </NavLink>
          ))}
        </nav>
      </aside>

      <div className="flex flex-col">
        <header className="flex h-14 items-center justify-between border-b border-border px-6">
          <div className="text-sm text-muted-foreground">
            {user?.name} · <span className="capitalize">{user?.role.replace('_', ' ')}</span>
          </div>
          <div className="flex items-center gap-2">
            <Button variant="ghost" size="icon" onClick={toggleDark} aria-label="Toggle theme">
              {dark ? <Sun /> : <Moon />}
            </Button>
            <Button variant="outline" size="sm" onClick={logout}>
              <LogOut />
              Logout
            </Button>
          </div>
        </header>
        <main className="flex-1 p-6">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
