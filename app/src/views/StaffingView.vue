<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api } from '@/lib/api'
import { date } from '@/lib/format'
import StatusBadge from '@/components/StatusBadge.vue'

interface ConsultantRow {
  id: number
  fullName: string
  email: string
  practiceLabel: string
  grade: string
  dailyRate: number
  skills: string[]
  allocation: number
  activeMissions: { missionId: number; title: string; clientName: string; allocation: number }[]
}

interface MissionRow {
  id: number
  clientName: string
  title: string
  practiceLabel: string
  status: string
  startDate: string
  endDate: string
  budgetDays: number
  teamSize: number
  assignments?: AssignmentRow[]
}

interface AssignmentRow {
  id: number
  consultantName: string
  startDate: string
  endDate: string
  allocation: number
  dailyRate: number
}

const tab = ref<'consultants' | 'missions'>('consultants')
const consultants = ref<ConsultantRow[]>([])
const missions = ref<MissionRow[]>([])
const selectedMission = ref<MissionRow | null>(null)

const gradeLabels: Record<string, string> = {
  junior: 'Junior',
  confirme: 'Confirmé',
  senior: 'Senior',
  manager: 'Manager',
}

onMounted(async () => {
  ;[consultants.value, missions.value] = await Promise.all([
    api.get<ConsultantRow[]>('/api/staffing/consultants'),
    api.get<MissionRow[]>('/api/staffing/missions'),
  ])
})

async function openMission(id: number) {
  selectedMission.value = await api.get<MissionRow>(`/api/staffing/missions/${id}`)
}

const allocationTone = (allocation: number) =>
  allocation === 0 ? 'text-warn' : allocation >= 90 ? 'text-ink' : 'text-ink/70'
</script>

<template>
  <div>
    <div class="mb-5 flex gap-1 rounded-md border border-ink/10 bg-surface p-1 w-fit">
      <button
        v-for="t in (['consultants', 'missions'] as const)"
        :key="t"
        class="rounded px-3.5 py-1.5 text-[13px] font-medium transition-colors"
        :class="tab === t ? 'bg-primary text-white' : 'text-ink/60 hover:text-ink'"
        @click="tab = t"
      >
        {{ t === 'consultants' ? `Consultants (${consultants.length})` : `Missions (${missions.length})` }}
      </button>
    </div>

    <!-- Consultants -->
    <div v-if="tab === 'consultants'" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
      <article v-for="consultant in consultants" :key="consultant.id" class="rounded-lg border border-ink/8 bg-surface p-4">
        <div class="flex items-start justify-between gap-2">
          <div>
            <h2 class="text-[14.5px] font-semibold">{{ consultant.fullName }}</h2>
            <p class="text-[12.5px] text-ink/55">{{ gradeLabels[consultant.grade] ?? consultant.grade }} · {{ consultant.practiceLabel }}</p>
          </div>
          <span class="tnum shrink-0 text-[13px] font-semibold" :class="allocationTone(consultant.allocation)">
            {{ consultant.allocation === 0 ? 'Intercontrat' : `${consultant.allocation} %` }}
          </span>
        </div>

        <div class="mt-2.5 h-1.5 overflow-hidden rounded-full bg-ink/6" role="img" :aria-label="`Charge : ${consultant.allocation} %`">
          <div class="h-full rounded-full bg-primary" :style="{ width: `${consultant.allocation}%` }" />
        </div>

        <div class="mt-3 flex flex-wrap gap-1.5">
          <span v-for="skill in consultant.skills" :key="skill" class="rounded bg-ink/5 px-2 py-0.5 text-[11.5px] text-ink/65">
            {{ skill }}
          </span>
        </div>

        <ul v-if="consultant.activeMissions.length" class="mt-3 space-y-1 border-t border-ink/6 pt-2.5">
          <li v-for="m in consultant.activeMissions" :key="m.missionId" class="truncate text-[12.5px] text-ink/60">
            {{ m.title }} <span class="text-ink/40">· {{ m.clientName }} · {{ m.allocation }} %</span>
          </li>
        </ul>
      </article>
    </div>

    <!-- Missions -->
    <div v-else class="overflow-x-auto rounded-lg border border-ink/8 bg-surface">
      <table class="w-full min-w-[720px] text-[13.5px]">
        <thead>
          <tr class="border-b border-ink/8 text-left text-[11.5px] uppercase tracking-[0.06em] text-ink/45">
            <th class="px-[18px] py-3 font-medium">Mission</th>
            <th class="px-[18px] py-3 font-medium">Client</th>
            <th class="px-[18px] py-3 font-medium">Practice</th>
            <th class="px-[18px] py-3 font-medium">Statut</th>
            <th class="px-[18px] py-3 font-medium">Période</th>
            <th class="px-4 py-3 text-right font-medium">Budget (j)</th>
            <th class="px-4 py-3 text-right font-medium">Équipe</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="mission in missions"
            :key="mission.id"
            class="cursor-pointer border-b border-ink/5 transition-colors last:border-0 hover:bg-primary-soft/40"
            @click="openMission(mission.id)"
          >
            <td class="px-[18px] py-[11px] font-medium">{{ mission.title }}</td>
            <td class="px-[18px] py-[11px] text-ink/60">{{ mission.clientName }}</td>
            <td class="px-[18px] py-[11px] text-ink/60">{{ mission.practiceLabel }}</td>
            <td class="px-[18px] py-[11px]"><StatusBadge :status="mission.status" /></td>
            <td class="px-[18px] py-[11px] text-[12.5px] text-ink/60">{{ date(mission.startDate) }} → {{ date(mission.endDate) }}</td>
            <td class="tnum px-[18px] py-[11px] text-right">{{ mission.budgetDays }}</td>
            <td class="tnum px-[18px] py-[11px] text-right">{{ mission.teamSize }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Volet mission -->
    <div v-if="selectedMission" class="fixed inset-0 z-20 flex justify-end bg-shell/40" @click.self="selectedMission = null">
      <div class="h-full w-full max-w-md overflow-y-auto bg-surface p-6 shadow-2xl">
        <div class="mb-1 flex items-start justify-between">
          <h2 class="font-display text-xl font-semibold tracking-tight">{{ selectedMission.title }}</h2>
          <button class="rounded p-1 text-ink/40 hover:text-ink" aria-label="Fermer" @click="selectedMission = null">✕</button>
        </div>
        <p class="text-[13px] text-ink/55">{{ selectedMission.clientName }} · {{ selectedMission.practiceLabel }}</p>
        <div class="mt-3 flex items-center gap-3">
          <StatusBadge :status="selectedMission.status" />
          <span class="text-[12.5px] text-ink/55">{{ date(selectedMission.startDate) }} → {{ date(selectedMission.endDate) }}</span>
        </div>

        <h3 class="mt-7 mb-2.5 text-[12px] font-medium uppercase tracking-[0.08em] text-ink/45">Équipe affectée</h3>
        <ul class="space-y-2.5">
          <li v-for="assignment in selectedMission.assignments" :key="assignment.id" class="rounded-md border border-ink/8 p-3">
            <div class="flex items-center justify-between">
              <p class="text-[13.5px] font-medium">{{ assignment.consultantName }}</p>
              <span class="tnum text-[13px] text-ink/70">{{ assignment.allocation }} %</span>
            </div>
            <p class="tnum mt-0.5 text-[12.5px] text-ink/55">
              {{ date(assignment.startDate) }} → {{ date(assignment.endDate) }} · TJM {{ assignment.dailyRate }} €
            </p>
          </li>
          <li v-if="!selectedMission.assignments?.length" class="text-[13px] text-ink/45">
            Personne n'est affecté à cette mission pour l'instant.
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>
