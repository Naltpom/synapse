<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { api } from '@/lib/api'
import Skeleton from '@/components/Skeleton.vue'
import { currentUser } from '@/lib/session'
import { refreshNavCounters } from '@/lib/nav'

interface CalendarDay {
  date: string
  weekend: boolean
}

interface CalendarRow {
  consultant: string
  cells: { state: 'mission' | 'conge_valide' | 'conge_attente' | 'weekend' | 'dispo' }[]
}

interface Calendar {
  days: CalendarDay[]
  rows: CalendarRow[]
}

interface Leave {
  id: number
  consultantName: string
  typeLabel: string
  startDate: string
  endDate: string
  days: number
  status: string
  source: string
  createdAt: string
}

const calendar = ref<Calendar | null>(null)
const pending = ref<Leave[]>([])

const canDecide = computed(() =>
  (currentUser.value?.roles ?? []).some((r) => r === 'ROLE_MANAGER' || r === 'ROLE_ADMIN'),
)

async function reload() {
  ;[calendar.value, pending.value] = await Promise.all([
    api.get<Calendar>('/api/hr/calendar'),
    api.get<Leave[]>('/api/hr/leaves?status=pending_approval'),
  ])
}

onMounted(reload)

async function decide(leave: Leave, decision: 'approve' | 'reject') {
  await api.post(`/api/hr/leaves/${leave.id}/${decision}`, {})
  await Promise.all([reload(), refreshNavCounters()])
}

const monthTitle = computed(() => {
  if (!calendar.value?.days.length) return 'Calendrier d\'équipe'
  const first = new Date(calendar.value.days[0].date)
  const month = new Intl.DateTimeFormat('fr-FR', { month: 'long', year: 'numeric' }).format(first)
  return `Calendrier d'équipe — ${month}`
})

function dayHeader(day: CalendarDay): string {
  const date = new Date(day.date)
  const wd = new Intl.DateTimeFormat('fr-FR', { weekday: 'short' }).format(date).slice(0, 2)
  const label = wd.charAt(0).toUpperCase() + wd.slice(1)
  return day.weekend ? label : `${label} ${date.getDate()}`
}

function dateLine(leave: Leave): string {
  const fmt = new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'short' })
  const start = fmt.format(new Date(leave.startDate))
  const unit = leave.days > 1 ? 'jours' : 'jour'
  if (leave.startDate === leave.endDate) return `${cap(start)} · ${leave.days} ${unit}`
  return `${cap(start)} → ${fmt.format(new Date(leave.endDate))} · ${leave.days} ${unit}`
}

const cap = (s: string) => s.charAt(0).toUpperCase() + s.slice(1)

function provenance(leave: Leave): string {
  const minutes = Math.max(1, Math.round((Date.now() - new Date(leave.createdAt).getTime()) / 60000))
  const ago = minutes < 60
    ? `il y a ${minutes} min`
    : minutes < 1440
      ? `il y a ${Math.round(minutes / 60)} h`
      : `il y a ${Math.round(minutes / 1440)} j`
  return leave.source === 'mcp' ? `créée via assistant · ${ago}` : `créée ${ago}`
}

const cellClasses: Record<CalendarRow['cells'][number]['state'], string> = {
  mission: 'bg-primary',
  conge_valide: 'bg-warn',
  conge_attente: 'bg-surface border-[1.5px] border-dashed border-warn',
  weekend: 'bg-ink/3',
  dispo: 'bg-ink/6',
}
</script>

<template>
  <div class="grid items-start gap-[22px] xl:grid-cols-[1fr_380px]">
    <!-- Calendrier d'équipe -->
    <section class="overflow-hidden rounded-lg border border-ink/8 bg-surface">
      <h2 class="border-b border-ink/7 px-[18px] py-4 font-display text-[15px] font-semibold tracking-tight">
        {{ monthTitle }}
      </h2>
      <div v-if="calendar" class="p-[18px]">
        <div class="mb-1.5 grid grid-cols-[150px_repeat(10,1fr)] gap-1 text-[11px] text-ink/45">
          <span />
          <span v-for="day in calendar.days" :key="day.date" class="text-center" :class="{ 'text-ink/30': day.weekend }">
            {{ dayHeader(day) }}
          </span>
        </div>
        <div
          v-for="row in calendar.rows"
          :key="row.consultant"
          class="mb-[5px] grid grid-cols-[150px_repeat(10,1fr)] items-center gap-1"
        >
          <span class="truncate text-[12.5px]">{{ row.consultant }}</span>
          <span
            v-for="(cell, i) in row.cells"
            :key="i"
            class="h-[22px] rounded-[3px]"
            :class="cellClasses[cell.state]"
          />
        </div>

        <div class="mt-3.5 flex gap-4 text-[11px] text-ink/50">
          <span><span class="mr-[5px] inline-block h-[9px] w-[9px] rounded-[2px] bg-primary" />Mission</span>
          <span><span class="mr-[5px] inline-block h-[9px] w-[9px] rounded-[2px] bg-warn" />Congé validé</span>
          <span><span class="mr-[5px] inline-block h-[9px] w-[9px] rounded-[2px] border-[1.5px] border-dashed border-warn bg-surface" />Congé en attente</span>
          <span><span class="mr-[5px] inline-block h-[9px] w-[9px] rounded-[2px] bg-ink/6" />Disponible</span>
        </div>
      </div>
      <div v-else class="p-[18px]"><Skeleton :lines="6" /></div>
    </section>

    <!-- Validations en attente -->
    <aside class="overflow-hidden rounded-lg border border-ink/8 bg-surface">
      <div class="flex items-center justify-between border-b border-ink/7 px-[18px] py-4">
        <h2 class="font-display text-[15px] font-semibold tracking-tight">Validations en attente</h2>
        <span class="tnum rounded-[9px] bg-warn/10 px-2 py-0.5 font-mono text-[11px] font-medium text-warn">{{ pending.length }}</span>
      </div>

      <div v-for="leave in pending" :key="leave.id" class="border-b border-ink/6 px-[18px] py-3.5 last:border-0">
        <p class="text-[13px] font-medium">{{ leave.consultantName }} — {{ leave.typeLabel }}</p>
        <p class="mt-0.5 text-[12px] text-ink/55">{{ dateLine(leave) }}</p>
        <p class="mt-[3px] font-mono text-[11px] text-ink/45">{{ provenance(leave) }}</p>
        <div v-if="canDecide" class="mt-2.5 flex gap-2">
          <button
            class="rounded-[5px] bg-ok px-3 py-[5px] text-[12px] font-medium text-white transition-opacity hover:opacity-90"
            @click="decide(leave, 'approve')"
          >
            Valider
          </button>
          <button
            class="rounded-[5px] border border-alert/30 px-3 py-[5px] text-[12px] font-medium text-alert transition-colors hover:bg-alert/5"
            @click="decide(leave, 'reject')"
          >
            Refuser
          </button>
        </div>
      </div>

      <p v-if="pending.length === 0" class="px-[18px] py-8 text-center text-[13px] text-ink/45">
        Aucune demande en attente — tout est validé.
      </p>
    </aside>
  </div>
</template>
