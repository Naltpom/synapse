<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api, ApiError } from '@/lib/api'
import Skeleton from '@/components/Skeleton.vue'
import { euro, euroCompact } from '@/lib/format'
import StatusBadge from '@/components/StatusBadge.vue'

interface MarginRow {
  id: number
  title: string
  clientName: string
  practiceLabel: string
  status: string
  revenue: number
  cost: number
  margin: number
  marginRate: number | null
}

interface Margins {
  totals: { revenue: number; cost: number; margin: number; marginRate: number | null }
  missions: MarginRow[]
  clients: { clientName: string; revenue: number; cost: number; margin: number; marginRate: number | null }[]
}

const data = ref<Margins | null>(null)
const forbidden = ref(false)

onMounted(async () => {
  try {
    data.value = await api.get<Margins>('/api/finance/margins')
  } catch (e) {
    forbidden.value = e instanceof ApiError && e.status === 403
    if (!forbidden.value) throw e
  }
})
</script>

<template>
  <div v-if="forbidden" class="rounded-lg border border-ink/8 bg-surface p-10 text-center text-[13.5px] text-ink/45">
    Les marges sont réservées aux managers et à la direction.
  </div>

  <div v-else-if="data">
    <!-- Totaux -->
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
      <div class="min-w-0 rounded-lg border border-ink/8 bg-surface p-4">
        <p class="text-[11.5px] font-medium uppercase tracking-[0.08em] text-ink/45">CA facturé</p>
        <p class="tnum mt-1.5 font-display text-[24px] font-semibold leading-none tracking-tight">{{ euroCompact(data.totals.revenue) }}</p>
      </div>
      <div class="min-w-0 rounded-lg border border-ink/8 bg-surface p-4">
        <p class="text-[11.5px] font-medium uppercase tracking-[0.08em] text-ink/45">Coût estimé</p>
        <p class="tnum mt-1.5 font-display text-[24px] font-semibold leading-none tracking-tight">{{ euroCompact(data.totals.cost) }}</p>
      </div>
      <div class="min-w-0 rounded-lg border border-ink/8 bg-surface p-4">
        <p class="text-[11.5px] font-medium uppercase tracking-[0.08em] text-ink/45">Marge brute</p>
        <p class="tnum mt-1.5 font-display text-[24px] font-semibold leading-none tracking-tight" :class="data.totals.margin < 0 ? 'text-alert' : 'text-ok'">
          {{ euroCompact(data.totals.margin) }}
        </p>
      </div>
      <div class="min-w-0 rounded-lg border border-ink/8 bg-surface p-4">
        <p class="text-[11.5px] font-medium uppercase tracking-[0.08em] text-ink/45">Taux de marge</p>
        <p class="tnum mt-1.5 font-display text-[24px] font-semibold leading-none tracking-tight">
          {{ data.totals.marginRate !== null ? `${data.totals.marginRate} %` : '—' }}
        </p>
      </div>
    </div>

    <div class="grid items-start gap-[22px] xl:grid-cols-[1fr_380px]">
      <!-- Marge par mission -->
      <section class="overflow-x-auto rounded-lg border border-ink/8 bg-surface">
        <h2 class="border-b border-ink/7 px-[18px] py-4 font-display text-[15px] font-semibold tracking-tight">Marge par mission</h2>
        <table class="w-full min-w-[640px] text-[13.5px]">
          <thead>
            <tr class="border-b border-ink/8 text-left text-[11.5px] uppercase tracking-[0.06em] text-ink/45">
              <th class="px-[18px] py-3 font-medium">Mission</th>
              <th class="px-[18px] py-3 font-medium">Statut</th>
              <th class="px-[18px] py-3 text-right font-medium">CA facturé</th>
              <th class="px-[18px] py-3 text-right font-medium">Coût estimé</th>
              <th class="px-[18px] py-3 text-right font-medium">Marge</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in data.missions" :key="row.id" class="border-b border-ink/5 last:border-0">
              <td class="px-[18px] py-[11px]">
                <p class="font-medium">{{ row.title }}</p>
                <p class="text-[11.5px] text-ink/45">{{ row.clientName }} · {{ row.practiceLabel }}</p>
              </td>
              <td class="px-[18px] py-[11px]"><StatusBadge :status="row.status" /></td>
              <td class="tnum px-[18px] py-[11px] text-right">{{ row.revenue > 0 ? euro(row.revenue) : '—' }}</td>
              <td class="tnum px-[18px] py-[11px] text-right text-ink/60">{{ euro(row.cost) }}</td>
              <td class="tnum px-[18px] py-[11px] text-right font-medium" :class="row.margin < 0 ? 'text-alert' : 'text-ok'">
                {{ euro(row.margin) }}
                <span v-if="row.marginRate !== null" class="tnum ml-1 text-[11.5px] font-normal text-ink/45">{{ row.marginRate }} %</span>
              </td>
            </tr>
          </tbody>
        </table>
      </section>

      <!-- Par client + méthodo -->
      <div class="flex flex-col gap-[22px]">
        <section class="overflow-hidden rounded-lg border border-ink/8 bg-surface">
          <h2 class="border-b border-ink/7 px-[18px] py-4 font-display text-[15px] font-semibold tracking-tight">Par client</h2>
          <div v-for="client in data.clients" :key="client.clientName" class="flex items-baseline justify-between border-b border-ink/6 px-[18px] py-2.5 last:border-0">
            <span class="truncate text-[13px] font-medium">{{ client.clientName }}</span>
            <span class="tnum text-[13px]" :class="client.margin < 0 ? 'text-alert' : 'text-ok'">
              {{ euroCompact(client.margin) }}
              <span v-if="client.marginRate !== null" class="text-[11.5px] text-ink/45"> · {{ client.marginRate }} %</span>
            </span>
          </div>
        </section>

        <section class="rounded-lg border border-dashed border-ink/15 bg-surface p-[18px]">
          <h2 class="font-display text-[14px] font-semibold tracking-tight">Méthode de calcul</h2>
          <p class="mt-2 text-[12.5px] leading-relaxed text-ink/55">
            Coût estimé = jours ouvrés écoulés de chaque affectation × taux d'allocation ×
            coût jour chargé du consultant. Estimation de pilotage : le coût réel sera issu
            des CRA validés.
          </p>
        </section>
      </div>
    </div>
  </div>

  <div v-else class="rounded-lg border border-ink/8 bg-surface p-6"><Skeleton :lines="8" /></div>
</template>
