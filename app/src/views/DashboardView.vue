<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api } from '@/lib/api'
import { isAdmin } from '@/lib/session'
import { euro, euroCompact } from '@/lib/format'
import KpiTile from '@/components/KpiTile.vue'
import MonthlyBarChart, { type MonthPoint } from '@/components/MonthlyBarChart.vue'
import PracticeBars, { type PracticeRow } from '@/components/PracticeBars.vue'

interface Dashboard {
  staffing: { consultants: number; staffingRate: number; bench: number }
  revenue: { collectedYtd: number; overdueCount: number; overdueAmount: number }
  pipeline: { openCount: number; weightedAmount: number }
  missions: { active: number }
  revenueByMonth: MonthPoint[]
  practiceDistribution: PracticeRow[]
  recentActivity: {
    id: number
    occurredAt: string
    actor: string | null
    action: string
    subjectType: string
    subjectId: string | null
  }[]
}

const data = ref<Dashboard | null>(null)

onMounted(async () => {
  data.value = await api.get<Dashboard>('/api/dashboard')
})

const time = (iso: string) =>
  new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }).format(new Date(iso))
</script>

<template>
  <div v-if="data" class="space-y-6">
    <!-- KPIs -->
    <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
      <KpiTile label="Taux de staffing" :value="`${data.staffing.staffingRate} %`" :hint="`${data.staffing.bench} en intercontrat`" />
      <KpiTile label="Consultants" :value="String(data.staffing.consultants)" hint="effectif production" />
      <KpiTile label="Missions actives" :value="String(data.missions.active)" />
      <KpiTile label="CA encaissé (année)" :value="euroCompact(data.revenue.collectedYtd)" hint="factures payées" />
      <KpiTile label="Pipeline pondéré" :value="euroCompact(data.pipeline.weightedAmount)" :hint="`${data.pipeline.openCount} opportunités ouvertes`" />
      <KpiTile
        label="Factures en retard"
        :value="String(data.revenue.overdueCount)"
        :hint="euro(data.revenue.overdueAmount)"
        :alert="data.revenue.overdueCount > 0"
      />
    </div>

    <div class="grid gap-6 xl:grid-cols-5">
      <!-- Facturation 12 mois -->
      <section class="rounded-lg border border-ink/8 bg-white p-5 xl:col-span-3">
        <h2 class="font-display text-[15px] font-semibold tracking-tight">Facturation émise — 12 derniers mois</h2>
        <p class="mb-4 text-[12.5px] text-ink/50">Montants HT, brouillons exclus</p>
        <MonthlyBarChart :points="data.revenueByMonth" />
      </section>

      <!-- Practices -->
      <section class="rounded-lg border border-ink/8 bg-white p-5 xl:col-span-2">
        <h2 class="font-display text-[15px] font-semibold tracking-tight">Effectifs par practice</h2>
        <p class="mb-4 text-[12.5px] text-ink/50">Consultants et taux d'occupation</p>
        <PracticeBars :rows="data.practiceDistribution" />
      </section>
    </div>

    <!-- Activité récente : le feed façon terminal, seule surface sombre de l'UI (direction uniquement) -->
    <section v-if="isAdmin" class="rounded-lg bg-ink p-5 text-white">
      <div class="mb-3 flex items-center justify-between">
        <h2 class="font-display text-[15px] font-semibold tracking-tight">Activité récente</h2>
        <router-link v-if="isAdmin" to="/audit" class="text-[12.5px] text-white/50 transition-colors hover:text-white">
          Journal complet →
        </router-link>
      </div>
      <ul class="space-y-1.5 font-mono text-[12.5px]">
        <li v-for="entry in data.recentActivity" :key="entry.id" class="flex gap-3 text-white/75">
          <span class="tnum shrink-0 text-white/40">{{ time(entry.occurredAt) }}</span>
          <span class="shrink-0 text-primary" style="color: #6b9aff">{{ entry.action }}</span>
          <span class="truncate">
            {{ entry.subjectType }}<template v-if="entry.subjectId">#{{ entry.subjectId }}</template>
            <span class="text-white/40"> · {{ entry.actor ?? 'système' }}</span>
          </span>
        </li>
      </ul>
    </section>
  </div>

  <p v-else class="py-16 text-center text-[13.5px] text-ink/45">Chargement de la vue d'ensemble…</p>
</template>
