import { ref } from 'vue'
import { api } from './api'

export interface NavCounters {
  staffingBench: number
  billingOverdue: number
  hrPending: number
}

export const navCounters = ref<NavCounters | null>(null)

/** Rafraîchi au montage du shell et après toute action qui change un compteur (ex. validation de congé). */
export async function refreshNavCounters(): Promise<void> {
  try {
    navCounters.value = await api.get<NavCounters>('/api/nav-counters')
  } catch {
    navCounters.value = null
  }
}
