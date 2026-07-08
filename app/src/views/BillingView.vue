<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { api } from '@/lib/api'
import { euro, date } from '@/lib/format'
import StatusBadge from '@/components/StatusBadge.vue'

interface InvoiceRow {
  id: number
  number: string
  clientName: string
  label: string
  amountHt: number
  status: string
  issuedAt: string
  dueAt: string
  paidAt: string | null
}

interface InvoicesResponse {
  totals: { count: number; amountHt: number }
  items: InvoiceRow[]
}

const statusFilter = ref('')
const data = ref<InvoicesResponse | null>(null)

const filters = [
  { value: '', label: 'Toutes' },
  { value: 'brouillon', label: 'Brouillons' },
  { value: 'envoyee', label: 'Envoyées' },
  { value: 'payee', label: 'Payées' },
  { value: 'en_retard', label: 'En retard' },
]

async function reload() {
  const query = statusFilter.value ? `?status=${statusFilter.value}` : ''
  data.value = await api.get<InvoicesResponse>(`/api/billing/invoices${query}`)
}

onMounted(reload)
watch(statusFilter, reload)
</script>

<template>
  <div>
    <div class="mb-5 flex items-center justify-between">
      <div class="flex gap-1 rounded-md border border-ink/10 bg-surface p-1">
        <button
          v-for="filter in filters"
          :key="filter.value"
          class="rounded px-3 py-1.5 text-[13px] font-medium transition-colors"
          :class="statusFilter === filter.value ? 'bg-primary text-white' : 'text-ink/60 hover:text-ink'"
          @click="statusFilter = filter.value"
        >
          {{ filter.label }}
        </button>
      </div>
      <p v-if="data" class="text-[13px] text-ink/55">
        {{ data.totals.count }} factures · <span class="tnum font-medium text-ink/80">{{ euro(data.totals.amountHt) }} HT</span>
      </p>
    </div>

    <div class="overflow-hidden rounded-lg border border-ink/8 bg-surface">
      <table class="w-full text-[13.5px]">
        <thead>
          <tr class="border-b border-ink/8 text-left text-[11.5px] uppercase tracking-[0.06em] text-ink/45">
            <th class="px-[18px] py-3 font-medium">N°</th>
            <th class="px-[18px] py-3 font-medium">Client</th>
            <th class="px-[18px] py-3 font-medium">Libellé</th>
            <th class="px-4 py-3 text-right font-medium">Montant HT</th>
            <th class="px-[18px] py-3 font-medium">Émise le</th>
            <th class="px-[18px] py-3 font-medium">Échéance</th>
            <th class="px-[18px] py-3 font-medium">Statut</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="invoice in data?.items" :key="invoice.id" class="border-b border-ink/5 last:border-0">
            <td class="px-[18px] py-[11px] font-mono text-[12.5px] text-ink/70">{{ invoice.number }}</td>
            <td class="px-[18px] py-[11px] font-medium">{{ invoice.clientName }}</td>
            <td class="max-w-64 truncate px-[18px] py-[11px] text-ink/60">{{ invoice.label }}</td>
            <td class="tnum px-[18px] py-[11px] text-right">{{ euro(invoice.amountHt) }}</td>
            <td class="tnum px-[18px] py-[11px] text-[12.5px] text-ink/60">{{ date(invoice.issuedAt) }}</td>
            <td class="tnum px-[18px] py-[11px] text-[12.5px]" :class="invoice.status === 'en_retard' ? 'font-medium text-alert' : 'text-ink/60'">
              {{ date(invoice.dueAt) }}
            </td>
            <td class="px-[18px] py-[11px]"><StatusBadge :status="invoice.status" /></td>
          </tr>
        </tbody>
      </table>
      <p v-if="data && data.items.length === 0" class="px-4 py-10 text-center text-[13px] text-ink/45">
        Aucune facture avec ce statut.
      </p>
    </div>
  </div>
</template>
