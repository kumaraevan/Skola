import { createContext, useContext, useEffect, useState, type ReactNode } from 'react'
import { api, clearToken, getToken, setToken } from '@/lib/api'

export type Role = 'super_admin' | 'school_admin' | 'teacher' | 'parent' | 'student'

export type User = {
  id: number
  name: string
  email: string
  role: Role
  school_id: number | null
}

type AuthContextValue = {
  user: User | null
  loading: boolean
  login: (email: string, password: string) => Promise<void>
  logout: () => Promise<void>
}

const AuthContext = createContext<AuthContextValue | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    if (!getToken()) {
      setLoading(false)
      return
    }
    api
      .get<User>('/me')
      .then((res) => setUser(res.data))
      .catch(() => clearToken())
      .finally(() => setLoading(false))
  }, [])

  const login = async (email: string, password: string) => {
    const res = await api.post<{ token: string; user: User }>('/login', { email, password })
    setToken(res.data.token)
    setUser(res.data.user)
  }

  const logout = async () => {
    try {
      await api.post('/logout')
    } finally {
      clearToken()
      setUser(null)
    }
  }

  return <AuthContext value={{ user, loading, login, logout }}>{children}</AuthContext>
}

// eslint-disable-next-line react-refresh/only-export-components
export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used within AuthProvider')
  return ctx
}
