import axios from 'axios'

const TOKEN_KEY = 'skola_token'

export const getToken = () => localStorage.getItem(TOKEN_KEY)
export const setToken = (t: string) => localStorage.setItem(TOKEN_KEY, t)
export const clearToken = () => localStorage.removeItem(TOKEN_KEY)

// baseURL '/api' is proxied to the Laravel server in dev (see vite.config.ts).
export const api = axios.create({
  baseURL: '/api',
  headers: { Accept: 'application/json' },
})

api.interceptors.request.use((config) => {
  const token = getToken()
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

api.interceptors.response.use(
  (res) => res,
  (err) => {
    // Token invalid/expired: drop it so the app redirects to login.
    if (err.response?.status === 401) clearToken()
    return Promise.reject(err)
  },
)
