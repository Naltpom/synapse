<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api, ApiError } from '@/lib/api'

interface AuditEntry {
  id: number
  occurredAt: string
  actor: string | null
  action: string
  subjectType: string
  subjectId: string | null
  changes: Record<string, unknown> | null
  ip: string | null
}

const entries = ref<AuditEntry[]>([])
const forbidden = ref(false)

onMounted(async () => {
  try {
    entries.value = await api.get<AuditEntry[]>('/api/audit')
  } catch (e) {
    forbidden.value = e instanceof ApiError && e.status === 403
    if (!forbidden.value) throw e
  }
})

const timestamp = (iso: string) =>
  new Intl.DateTimeFormat('fr-FR', {
    year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit', second: '2-digit',
  }).format(new Date(iso))

const actionColors: Record<string, string> = {
  create: '#7ee2a8',
  update: '#6b9aff',
  delete: '#ff8d92',
  login: '#c9b3ff',
  login_failure: '#ff8d92',
}
</script>

<template>
  <div class="rounded-lg bg-ink p-5 text-white">
    <div class="mb-4 flex items-center justify-between">
      <p class="text-[13px] text-white/55">
        Chaque écriture est journalisée automatiquement : acteur, action, objet, diff et adresse IP.
      </p>
      <span class="tnum font-mono text-[12px] text-white/40">{{ entries.length }} entrées</span>
    </div>

    <ol class="space-y-1 font-mono text-[12.5px] leading-relaxed">
      <li v-for="entry in entries" :key="entry.id" class="flex flex-wrap gap-x-3 border-b border-white/5 py-1.5 text-white/80 last:border-0">
        <span class="tnum text-white/40">{{ timestamp(entry.occurredAt) }}</span>
        <span :style="{ color: actionColors[entry.action] ?? '#ffffff' }" class="w-24">{{ entry.action }}</span>
        <span>
          {{ entry.subjectType }}<template v-if="entry.subjectId">#{{ entry.subjectId }}</template>
        </span>
        <span class="text-white/45">{{ entry.actor ?? 'système' }}</span>
        <span v-if="entry.ip" class="text-white/30">{{ entry.ip }}</span>
        <span v-if="entry.changes" class="basis-full pl-40 text-[11.5px] text-white/45">
          {{ JSON.stringify(entry.changes) }}
        </span>
      </li>
    </ol>

    <p v-if="forbidden" class="py-10 text-center text-[13px] text-white/40">
      Le journal d'audit est réservé à la direction. Connectez-vous avec un compte administrateur pour le consulter.
    </p>
    <p v-else-if="entries.length === 0" class="py-10 text-center text-[13px] text-white/40">
      Le journal est vide pour l'instant : chaque action d'écriture viendra s'inscrire ici.
    </p>
  </div>
</template>
