<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { api, ApiError } from '@/lib/api'
import { isManager } from '@/lib/session'
import { refreshNavCounters } from '@/lib/nav'
import StatusBadge from '@/components/StatusBadge.vue'

interface ConsultantOption {
  id: number
  fullName: string
}

interface GridLine {
  key: string
  label: string
  sublabel: string | null
  category: string
  cells: { date: string; fraction: number }[]
}

interface Grid {
  consultant: { id: number; name: string }
  week: { weekStart: string; status: 'draft' | 'submitted' | 'validated' }
  days: string[]
  lines: GridLine[]
}

const consultants = ref<ConsultantOption[]>([])
const consultantId = ref<number | null>(null)
const weekAnchor = ref(new Date().toISOString().slice(0, 10))
const grid = ref<Grid | null>(null)
const error = ref('')
const forbidden = ref(false)

const canValidate = isManager

const editable = computed(() => grid.value?.week.status === 'draft')

const weekTotal = computed(() =>
  grid.value?.lines.reduce((sum, line) => sum + line.cells.reduce((s, c) => s + (c?.fraction ?? 0), 0), 0) ?? 0,
)

function dayTotal(index: number): number {
  return grid.value?.lines.reduce((sum, line) => sum + (line.cells[index]?.fraction ?? 0), 0) ?? 0
}

async function reload() {
  if (consultantId.value === null) return
  error.value = ''
  try {
    grid.value = await api.get<Grid>(`/api/cra?consultantId=${consultantId.value}&week=${weekAnchor.value}`)
    forbidden.value = false
  } catch (e) {
    forbidden.value = e instanceof ApiError && e.status === 403
    if (!forbidden.value) throw e
  }
}

onMounted(async () => {
  const list = await api.get<ConsultantOption[]>('/api/staffing/consultants')
  consultants.value = list.map((c) => ({ id: c.id, fullName: c.fullName }))
  consultantId.value = consultants.value[0]?.id ?? null
})

watch([consultantId, weekAnchor], reload)

function shiftWeek(deltaDays: number) {
  const d = new Date(weekAnchor.value)
  d.setDate(d.getDate() + deltaDays)
  weekAnchor.value = d.toISOString().slice(0, 10)
}

const weekLabel = computed(() => {
  if (!grid.value) return ''
  const fmt = new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short' })
  return `${fmt.format(new Date(grid.value.days[0]))} → ${fmt.format(new Date(grid.value.days[4]))}`
})

const dayHeader = (iso: string) => {
  const label = new Intl.DateTimeFormat('fr-FR', { weekday: 'short', day: 'numeric' }).format(new Date(iso))
  return label.charAt(0).toUpperCase() + label.slice(1)
}

async function cycle(line: GridLine, cellIndex: number) {
  if (!editable.value || !grid.value || consultantId.value === null) return
  const cell = line.cells[cellIndex]
  const next = cell.fraction === 0 ? 1 : cell.fraction === 1 ? 0.5 : 0
  error.value = ''
  try {
    await api.put('/api/cra/entries', {
      consultantId: consultantId.value,
      date: cell.date,
      lineKey: line.key,
      fraction: next,
    })
    cell.fraction = next
  } catch (e) {
    if (e instanceof ApiError) error.value = e.details?.fraction ?? e.message
  }
}

async function submitWeek() {
  if (!grid.value || consultantId.value === null) return
  await api.post('/api/cra/submit', { consultantId: consultantId.value, week: grid.value.week.weekStart })
  await Promise.all([reload(), refreshNavCounters()])
}

async function validateWeek() {
  if (!grid.value || consultantId.value === null) return
  await api.post('/api/cra/validate', { consultantId: consultantId.value, week: grid.value.week.weekStart })
  await Promise.all([reload(), refreshNavCounters()])
}
</script>

<template>
  <div v-if="forbidden" class="rounded-lg border border-ink/8 bg-surface p-10 text-center text-[13.5px] text-ink/45">
    La saisie des CRA est réservée aux managers et à la direction.
  </div>

  <div v-else>
    <!-- Barre d'outils -->
    <div class="mb-5 flex flex-wrap items-center gap-3">
      <select
        v-model.number="consultantId"
        class="rounded-md border border-ink/12 bg-surface px-3 py-1.5 text-[13px] focus:border-primary"
      >
        <option v-for="c in consultants" :key="c.id" :value="c.id">{{ c.fullName }}</option>
      </select>

      <div class="flex items-center gap-1 rounded-md border border-ink/10 bg-surface p-1">
        <button class="rounded px-2 py-1 text-[13px] text-ink/60 hover:bg-ink/5" aria-label="Semaine précédente" @click="shiftWeek(-7)">‹</button>
        <span class="tnum px-2 text-[13px] font-medium">{{ weekLabel }}</span>
        <button class="rounded px-2 py-1 text-[13px] text-ink/60 hover:bg-ink/5" aria-label="Semaine suivante" @click="shiftWeek(7)">›</button>
      </div>

      <StatusBadge v-if="grid" :status="grid.week.status" />

      <div class="ml-auto flex items-center gap-3">
        <span class="tnum text-[13px] text-ink/55">Total : <span class="font-semibold text-ink">{{ weekTotal }}</span> / 5 j</span>
        <button
          v-if="grid && grid.week.status === 'draft' && weekTotal > 0"
          class="rounded-md bg-primary px-3.5 py-1.5 text-[13px] font-medium text-white transition-colors hover:bg-primary-strong"
          @click="submitWeek"
        >
          Soumettre la semaine
        </button>
        <button
          v-if="grid && grid.week.status === 'submitted' && canValidate"
          class="rounded-md bg-ok px-3.5 py-1.5 text-[13px] font-medium text-white transition-opacity hover:opacity-90"
          @click="validateWeek"
        >
          Valider la semaine
        </button>
      </div>
    </div>

    <p v-if="error" class="mb-4 rounded-md bg-alert/8 px-3 py-2 text-[13px] text-alert">{{ error }}</p>

    <!-- Grille -->
    <div v-if="grid" class="overflow-x-auto rounded-lg border border-ink/8 bg-surface">
      <div class="min-w-[620px]">
      <div class="grid grid-cols-[minmax(200px,1.2fr)_repeat(5,1fr)] border-b border-ink/8 text-[11.5px] uppercase tracking-[0.06em] text-ink/45">
        <span class="px-[18px] py-3 font-medium">Activité</span>
        <span v-for="day in grid.days" :key="day" class="px-2 py-3 text-center font-medium">{{ dayHeader(day) }}</span>
      </div>

      <div
        v-for="line in grid.lines"
        :key="line.key"
        class="grid grid-cols-[minmax(200px,1.2fr)_repeat(5,1fr)] items-center border-b border-ink/5 last:border-0"
      >
        <div class="min-w-0 px-[18px] py-2.5">
          <p class="truncate text-[13.5px]" :class="line.category === 'mission' ? 'font-medium' : 'text-ink/60'">{{ line.label }}</p>
          <p v-if="line.sublabel" class="truncate text-[11.5px] text-ink/45">{{ line.sublabel }}</p>
        </div>
        <div v-for="(cell, i) in line.cells" :key="cell.date" class="flex justify-center px-2 py-2">
          <button
            class="tnum h-8 w-full max-w-16 rounded-md border text-[12.5px] font-medium transition-colors"
            :class="[
              cell.fraction === 1 ? 'border-primary bg-primary text-white'
                : cell.fraction === 0.5 ? 'border-primary/40 bg-primary-soft text-primary-strong'
                : 'border-ink/10 text-ink/30 hover:border-ink/25',
              !editable && 'cursor-default opacity-70',
            ]"
            :aria-label="`${line.label} — ${cell.date}`"
            @click="cycle(line, i)"
          >
            {{ cell.fraction === 1 ? '1' : cell.fraction === 0.5 ? '½' : '·' }}
          </button>
        </div>
      </div>

      <!-- Totaux -->
      <div class="grid grid-cols-[minmax(200px,1.2fr)_repeat(5,1fr)] border-t border-ink/8 bg-cloud/60">
        <span class="px-[18px] py-2.5 text-[12.5px] font-medium text-ink/60">Total du jour</span>
        <span
          v-for="(day, i) in grid.days"
          :key="day"
          class="tnum px-2 py-2.5 text-center text-[12.5px] font-semibold"
          :class="dayTotal(i) > 1 ? 'text-alert' : dayTotal(i) === 1 ? 'text-ink' : 'text-ink/45'"
        >
          {{ dayTotal(i) }}
        </span>
      </div>
      </div>
    </div>

    <p v-if="grid && !editable" class="mt-3 text-[12.5px] text-ink/50">
      {{ grid.week.status === 'submitted' ? 'Semaine soumise — en attente de validation, la saisie est verrouillée.' : 'Semaine validée — la saisie est verrouillée.' }}
    </p>
  </div>
</template>
