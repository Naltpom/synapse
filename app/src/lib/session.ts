import { computed, ref } from 'vue'
import { api } from './api'

export interface SessionUser {
  email: string
  fullName: string
  jobTitle: string
  roles: string[]
}

export const currentUser = ref<SessionUser | null>(null)

export const isAdmin = computed(() => currentUser.value?.roles.includes('ROLE_ADMIN') ?? false)

export const isManager = computed(
  () => (currentUser.value?.roles ?? []).some((r) => r === 'ROLE_MANAGER' || r === 'ROLE_ADMIN'),
)

let checked = false

/** Restaure la session au premier accès (cookie déjà présent). */
export async function ensureSession(): Promise<SessionUser | null> {
  if (checked) return currentUser.value
  checked = true
  try {
    currentUser.value = await api.get<SessionUser>('/api/me')
  } catch {
    currentUser.value = null
  }
  return currentUser.value
}

export async function login(email: string, password: string): Promise<SessionUser> {
  const user = await api.post<SessionUser>('/api/login', { email, password })
  currentUser.value = user
  checked = true
  return user
}

export async function logout(): Promise<void> {
  await api.post('/api/logout', {})
  currentUser.value = null
  window.location.href = '/login'
}
