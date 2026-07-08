<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '@/lib/api'
import { euro, date } from '@/lib/format'
import StatusBadge from '@/components/StatusBadge.vue'

interface Overview {
  id: number
  name: string
  sector: string
  city: string
  status: string
  createdAt: string
  weightedPipeline: number
  contacts: { id: number; firstName: string; lastName: string; role: string; email: string; phone: string | null }[]
  opportunities: { id: number; title: string; practiceLabel: string; amount: number; stage: string; probability: number; expectedCloseAt: string }[]
  missions: { id: number; title: string; practiceLabel: string; status: string; startDate: string; endDate: string; budgetDays: number; teamSize: number }[]
  invoices: { id: number; number: string; label: string; amountHt: number; status: string; issuedAt: string; dueAt: string }[]
  kpis: { billedTotal: number; paidTotal: number; overdueAmount: number; activeMissions: number }
}

const route = useRoute()
const data = ref<Overview | null>(null)

onMounted(async () => {
  data.value = await api.get<Overview>(`/api/crm/clients/${route.params.id}/overview`)
})
</script>

<template>
  <div v-if="data">
    <router-link to="/crm" class="text-[12.5px] text-ink/50 transition-colors hover:text-primary">← CRM</router-link>

    <!-- En-tête client -->
    <div class="mt-3 mb-6 flex items-start justify-between">
      <div>
        <h2 class="font-display text-[22px] font-semibold tracking-tight">{{ data.name }}</h2>
        <p class="mt-1 text-[13px] text-ink/55">{{ data.sector }} · {{ data.city }} · client depuis le {{ date(data.createdAt) }}</p>
      </div>
      <StatusBadge :status="data.status" />
    </div>

    <!-- KPIs -->
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
      <div class="min-w-0 rounded-lg border border-ink/8 bg-white p-4">
        <p class="text-[11.5px] font-medium uppercase tracking-[0.08em] text-ink/45">CA facturé</p>
        <p class="tnum mt-1.5 font-display text-[24px] font-semibold leading-none tracking-tight">{{ euro(data.kpis.billedTotal) }}</p>
      </div>
      <div class="min-w-0 rounded-lg border border-ink/8 bg-white p-4">
        <p class="text-[11.5px] font-medium uppercase tracking-[0.08em] text-ink/45">Encaissé</p>
        <p class="tnum mt-1.5 font-display text-[24px] font-semibold leading-none tracking-tight">{{ euro(data.kpis.paidTotal) }}</p>
      </div>
      <div class="min-w-0 rounded-lg border border-ink/8 bg-white p-4">
        <p class="text-[11.5px] font-medium uppercase tracking-[0.08em]" :class="data.kpis.overdueAmount > 0 ? 'text-alert' : 'text-ink/45'">En retard</p>
        <p class="tnum mt-1.5 font-display text-[24px] font-semibold leading-none tracking-tight" :class="{ 'text-alert': data.kpis.overdueAmount > 0 }">
          {{ data.kpis.overdueAmount > 0 ? euro(data.kpis.overdueAmount) : '—' }}
        </p>
      </div>
      <div class="min-w-0 rounded-lg border border-ink/8 bg-white p-4">
        <p class="text-[11.5px] font-medium uppercase tracking-[0.08em] text-ink/45">Pipeline pondéré</p>
        <p class="tnum mt-1.5 font-display text-[24px] font-semibold leading-none tracking-tight">{{ data.weightedPipeline > 0 ? euro(data.weightedPipeline) : '—' }}</p>
      </div>
    </div>

    <div class="grid items-start gap-[22px] xl:grid-cols-[1fr_380px]">
      <div class="flex min-w-0 flex-col gap-[22px]">
        <!-- Missions -->
        <section class="overflow-hidden rounded-lg border border-ink/8 bg-white">
          <h3 class="border-b border-ink/7 px-[18px] py-4 font-display text-[15px] font-semibold tracking-tight">
            Missions <span class="text-ink/40">({{ data.missions.length }})</span>
          </h3>
          <table v-if="data.missions.length" class="w-full text-[13.5px]">
            <tbody>
              <tr v-for="mission in data.missions" :key="mission.id" class="border-b border-ink/5 last:border-0">
                <td class="px-[18px] py-[11px] font-medium">{{ mission.title }}</td>
                <td class="px-[18px] py-[11px] text-ink/60">{{ mission.practiceLabel }}</td>
                <td class="tnum px-[18px] py-[11px] text-[12.5px] text-ink/60">{{ date(mission.startDate) }} → {{ date(mission.endDate) }}</td>
                <td class="px-[18px] py-[11px]"><StatusBadge :status="mission.status" /></td>
              </tr>
            </tbody>
          </table>
          <p v-else class="px-[18px] py-6 text-[13px] text-ink/45">Aucune mission pour ce client.</p>
        </section>

        <!-- Factures -->
        <section class="overflow-hidden rounded-lg border border-ink/8 bg-white">
          <h3 class="border-b border-ink/7 px-[18px] py-4 font-display text-[15px] font-semibold tracking-tight">
            Factures <span class="text-ink/40">({{ data.invoices.length }})</span>
          </h3>
          <table v-if="data.invoices.length" class="w-full text-[13.5px]">
            <tbody>
              <tr v-for="invoice in data.invoices" :key="invoice.id" class="border-b border-ink/5 last:border-0">
                <td class="px-[18px] py-[11px] font-mono text-[12.5px] text-ink/70">{{ invoice.number }}</td>
                <td class="max-w-56 truncate px-[18px] py-[11px] text-ink/60">{{ invoice.label }}</td>
                <td class="tnum px-[18px] py-[11px] text-right">{{ euro(invoice.amountHt) }}</td>
                <td class="px-[18px] py-[11px]"><StatusBadge :status="invoice.status" /></td>
              </tr>
            </tbody>
          </table>
          <p v-else class="px-[18px] py-6 text-[13px] text-ink/45">Aucune facture pour ce client.</p>
        </section>
      </div>

      <div class="flex flex-col gap-[22px]">
        <!-- Contacts -->
        <section class="rounded-lg border border-ink/8 bg-white">
          <h3 class="border-b border-ink/7 px-[18px] py-4 font-display text-[15px] font-semibold tracking-tight">Contacts</h3>
          <div v-for="contact in data.contacts" :key="contact.id" class="border-b border-ink/6 px-[18px] py-3 last:border-0">
            <p class="text-[13.5px] font-medium">{{ contact.firstName }} {{ contact.lastName }} <span class="font-normal text-ink/50">· {{ contact.role }}</span></p>
            <p class="mt-0.5 font-mono text-[12px] text-ink/55">{{ contact.email }}</p>
          </div>
        </section>

        <!-- Opportunités -->
        <section class="rounded-lg border border-ink/8 bg-white">
          <h3 class="border-b border-ink/7 px-[18px] py-4 font-display text-[15px] font-semibold tracking-tight">Opportunités</h3>
          <div v-for="opp in data.opportunities" :key="opp.id" class="border-b border-ink/6 px-[18px] py-3 last:border-0">
            <div class="flex items-center justify-between gap-2">
              <p class="text-[13.5px] font-medium">{{ opp.title }}</p>
              <StatusBadge :status="opp.stage" />
            </div>
            <p class="tnum mt-1 text-[12.5px] text-ink/55">{{ euro(opp.amount) }} · {{ opp.probability }} % · échéance {{ date(opp.expectedCloseAt) }}</p>
          </div>
          <p v-if="data.opportunities.length === 0" class="px-[18px] py-6 text-[13px] text-ink/45">Aucune opportunité en cours.</p>
        </section>
      </div>
    </div>
  </div>

  <p v-else class="py-16 text-center text-[13.5px] text-ink/45">Chargement de la fiche client…</p>
</template>
