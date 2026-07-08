<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '@/lib/api'
import Skeleton from '@/components/Skeleton.vue'
import { isAdmin } from '@/lib/session'
import { euroCompact } from '@/lib/format'
import Sparkline from '@/components/Sparkline.vue'
import MonthlyBarChart, { type MonthPoint } from '@/components/MonthlyBarChart.vue'
import PracticeBars, { type PracticeRow } from '@/components/PracticeBars.vue'

interface Todo {
  severity: 'alert' | 'warn' | 'info'
  title: string
  subtitle: string
  action: string
  target: string
}

interface Dashboard {
  hero: {
    staffing: { value: number; consultants: number; bench: number; deltaPts: number; series: number[] }
    revenue: { collectedYtd: number; annualTarget: number; targetPercent: number; series: number[] }
    pipeline: { weightedAmount: number; openCount: number; negotiationCount: number; series: number[] }
    overdue: { amount: number; count: number; series: number[] }
  }
  revenueByMonth: MonthPoint[]
  practiceDistribution: PracticeRow[]
  todos: Todo[]
  recentActivity: {
    id: number
    actor: string | null
    action: string
    subjectType: string
    subjectId: string | null
  }[]
}

const router = useRouter()
const data = ref<Dashboard | null>(null)

onMounted(async () => {
  data.value = await api.get<Dashboard>('/api/dashboard')
})

const targetRoutes: Record<string, string> = {
  billing: '/facturation',
  staffing: '/staffing',
  projects: '/projets',
  crm: '/crm',
  leave: '/conges',
}

const severityDots: Record<Todo['severity'], string> = {
  alert: '#ed1c24',
  warn: '#b45309',
  info: '#0048fe',
}

const actionColors: Record<string, string> = {
  create: '#7ee2a8',
  update: '#6b9aff',
  delete: '#ff8d92',
  login: '#c9b3ff',
  login_failure: '#ff8d92',
}
</script>

<template>
  <div v-if="data">
    <!-- Héro : 4 métriques à sparklines, rendues dans le header sombre du shell -->
    <Teleport to="#hero-outlet">
      <div class="grid grid-cols-4 px-8 pt-1.5 pb-7 text-white">
        <div class="border-r border-white/9 pr-7">
          <p class="text-[11px] font-medium uppercase tracking-[0.12em] text-white/45">Taux de staffing</p>
          <div class="mt-2 flex items-end justify-between gap-3">
            <span class="tnum font-display text-[38px] font-semibold leading-none tracking-[-0.03em]">{{ data.hero.staffing.value }} %</span>
            <Sparkline :points="data.hero.staffing.series" />
          </div>
          <p class="mt-2 text-[12px] text-white/50">
            {{ data.hero.staffing.bench }} en intercontrat
            <template v-if="data.hero.staffing.deltaPts > 0"> · <span class="text-[#7ee2a8]">+{{ data.hero.staffing.deltaPts }} pts sur un mois</span></template>
          </p>
        </div>

        <div class="border-r border-white/9 px-7">
          <p class="text-[11px] font-medium uppercase tracking-[0.12em] text-white/45">CA encaissé {{ new Date().getFullYear() }}</p>
          <div class="mt-2 flex items-end justify-between gap-3">
            <span class="tnum font-display text-[38px] font-semibold leading-none tracking-[-0.03em]">{{ euroCompact(data.hero.revenue.collectedYtd) }}</span>
            <Sparkline :points="data.hero.revenue.series" />
          </div>
          <p class="mt-2 text-[12px] text-white/50">
            objectif annuel : {{ euroCompact(data.hero.revenue.annualTarget) }} · <span class="text-[#7ee2a8]">{{ data.hero.revenue.targetPercent }} %</span>
          </p>
        </div>

        <div class="border-r border-white/9 px-7">
          <p class="text-[11px] font-medium uppercase tracking-[0.12em] text-white/45">Pipeline pondéré</p>
          <div class="mt-2 flex items-end justify-between gap-3">
            <span class="tnum font-display text-[38px] font-semibold leading-none tracking-[-0.03em]">{{ euroCompact(data.hero.pipeline.weightedAmount) }}</span>
            <Sparkline :points="data.hero.pipeline.series" />
          </div>
          <p class="mt-2 text-[12px] text-white/50">
            {{ data.hero.pipeline.openCount }} opportunités · {{ data.hero.pipeline.negotiationCount }} en négociation
          </p>
        </div>

        <div class="pl-7">
          <p class="text-[11px] font-medium uppercase tracking-[0.12em] text-[#ff9ba0]">Retards de paiement</p>
          <div class="mt-2 flex items-end justify-between gap-3">
            <span class="tnum font-display text-[38px] font-semibold leading-none tracking-[-0.03em] text-[#ff9ba0]">{{ euroCompact(data.hero.overdue.amount) }}</span>
            <Sparkline :points="data.hero.overdue.series" />
          </div>
          <p class="mt-2 text-[12px] text-white/50">{{ data.hero.overdue.count }} factures · relance à faire</p>
        </div>
      </div>
    </Teleport>

    <div class="grid items-start gap-[22px] xl:grid-cols-[1fr_380px]">
      <div class="flex min-w-0 flex-col gap-[22px]">
        <!-- Facturation émise -->
        <section class="rounded-lg border border-ink/8 bg-surface p-5">
          <div class="flex items-baseline justify-between">
            <h2 class="font-display text-[15px] font-semibold tracking-tight">Facturation émise — 12 mois</h2>
            <div class="flex gap-3 text-[11.5px] text-ink/50">
              <span><span class="mr-[5px] inline-block h-2 w-2 rounded-[2px] bg-primary" />Payée</span>
              <span><span class="mr-[5px] inline-block h-2 w-2 rounded-[2px] bg-[#c9d8fb]" />En attente</span>
            </div>
          </div>
          <MonthlyBarChart :points="data.revenueByMonth" />
        </section>

        <!-- Occupation par practice -->
        <section class="rounded-lg border border-ink/8 bg-surface p-5">
          <h2 class="mb-3.5 font-display text-[15px] font-semibold tracking-tight">Occupation par practice</h2>
          <PracticeBars :rows="data.practiceDistribution" />
        </section>

        <!-- Feed audit condensé (direction uniquement) -->
        <section v-if="isAdmin && data.recentActivity.length" class="rounded-lg bg-shell px-5 py-4 text-white">
          <div class="flex flex-wrap items-center gap-3.5 font-mono text-[12px] text-white/70">
            <template v-for="(entry, i) in data.recentActivity" :key="entry.id">
              <span v-if="i > 0" class="text-white/30">·</span>
              <span :style="{ color: actionColors[entry.action] ?? '#fff' }">{{ entry.action }}</span>
              <span>{{ entry.subjectType }}<template v-if="entry.subjectId">#{{ entry.subjectId }}</template></span>
            </template>
            <router-link to="/audit" class="ml-auto font-sans text-[12px] text-white/45 transition-colors hover:text-white">
              Journal d'audit →
            </router-link>
          </div>
        </section>
      </div>

      <!-- À traiter -->
      <aside class="overflow-hidden rounded-lg border border-ink/8 bg-surface">
        <div class="flex items-center justify-between border-b border-ink/7 px-[18px] py-4">
          <h2 class="font-display text-[15px] font-semibold tracking-tight">À traiter</h2>
          <span class="tnum rounded-[9px] bg-primary/8 px-2 py-0.5 font-mono text-[11px] font-medium text-primary-strong">{{ data.todos.length }}</span>
        </div>
        <div v-for="todo in data.todos" :key="todo.title" class="flex gap-[11px] border-b border-ink/6 px-[18px] py-3.5 last:border-0">
          <span class="mt-[5px] h-2 w-2 flex-none rounded-full" :style="{ background: severityDots[todo.severity] }" />
          <div class="min-w-0 flex-1">
            <p class="text-[13px] font-medium">{{ todo.title }}</p>
            <p class="mt-0.5 text-[12px] text-ink/55">{{ todo.subtitle }}</p>
            <button
              class="mt-2 rounded-[5px] border border-primary/30 px-2.5 py-1 text-[12px] font-medium text-primary transition-colors hover:bg-primary-soft"
              @click="router.push(targetRoutes[todo.target] ?? '/dashboard')"
            >
              {{ todo.action }}
            </button>
          </div>
        </div>
        <p v-if="data.todos.length === 0" class="px-[18px] py-8 text-center text-[13px] text-ink/45">
          Rien à traiter — tout est sous contrôle.
        </p>
      </aside>
    </div>
  </div>

  <div v-else class="rounded-lg border border-ink/8 bg-surface p-6"><Skeleton :lines="8" /></div>
</template>
