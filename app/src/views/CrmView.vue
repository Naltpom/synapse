<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api, ApiError } from '@/lib/api'
import { euro } from '@/lib/format'
import StatusBadge from '@/components/StatusBadge.vue'

const router = useRouter()

interface ClientRow {
  id: number
  name: string
  sector: string
  city: string
  status: string
  createdAt: string
  contactCount: number
  opportunityCount: number
  weightedPipeline: number
}

interface Opportunity {
  id: number
  clientId: number
  clientName: string
  title: string
  practiceLabel: string
  amount: number
  stage: string
  probability: number
  expectedCloseAt: string
  owner: string
}

const tab = ref<'clients' | 'opportunities'>('clients')
const clients = ref<ClientRow[]>([])
const opportunities = ref<Opportunity[]>([])
const search = ref('')

const showCreate = ref(false)
const textFields = [
  { key: 'name', label: 'Nom' },
  { key: 'sector', label: 'Secteur' },
  { key: 'city', label: 'Ville' },
] as const
const form = ref({ name: '', sector: '', city: '', status: 'prospect' })
const formErrors = ref<Record<string, string>>({})
const listError = ref('')

const stages = ['qualification', 'proposition', 'negociation', 'gagnee', 'perdue']

const filteredClients = computed(() => {
  const needle = search.value.toLowerCase()
  return clients.value.filter((c) => c.name.toLowerCase().includes(needle))
})

async function reload() {
  listError.value = ''
  try {
    ;[clients.value, opportunities.value] = await Promise.all([
      api.get<ClientRow[]>('/api/crm/clients'),
      api.get<Opportunity[]>('/api/crm/opportunities'),
    ])
  } catch (e) {
    listError.value = e instanceof ApiError ? e.message : 'Chargement impossible.'
  }
}

onMounted(reload)

function openClient(id: number) {
  router.push({ name: 'client', params: { id } })
}

async function createClient() {
  formErrors.value = {}
  try {
    await api.post('/api/crm/clients', form.value)
    showCreate.value = false
    form.value = { name: '', sector: '', city: '', status: 'prospect' }
    await reload()
  } catch (e) {
    if (e instanceof ApiError && e.details) formErrors.value = e.details
  }
}

async function changeStage(opportunity: Opportunity, stage: string) {
  const previous = opportunity.stage
  try {
    const updated = await api.patch<Opportunity>(`/api/crm/opportunities/${opportunity.id}`, { stage })
    Object.assign(opportunity, updated)
  } catch (e) {
    // Rollback : le <select> est en liaison unidirectionnelle, on remet l'étape réelle.
    opportunity.stage = previous
    listError.value = e instanceof ApiError ? e.message : 'Mise à jour impossible.'
  }
}
</script>

<template>
  <div>
    <p v-if="listError" class="mb-4 rounded-md bg-alert/8 px-3 py-2 text-[13px] text-alert">{{ listError }}</p>

    <!-- Onglets + actions -->
    <div class="mb-5 flex items-center justify-between">
      <div class="flex gap-1 rounded-md border border-ink/10 bg-surface p-1">
        <button
          v-for="t in (['clients', 'opportunities'] as const)"
          :key="t"
          class="rounded px-3.5 py-1.5 text-[13px] font-medium transition-colors"
          :class="tab === t ? 'bg-primary text-white' : 'text-ink/60 hover:text-ink'"
          @click="tab = t"
        >
          {{ t === 'clients' ? `Clients (${clients.length})` : `Opportunités (${opportunities.length})` }}
        </button>
      </div>

      <div class="flex gap-3">
        <input
          v-if="tab === 'clients'"
          v-model="search"
          type="search"
          placeholder="Rechercher un client…"
          class="w-56 rounded-md border border-ink/12 bg-surface px-3 py-1.5 text-[13px] focus:border-primary"
        />
        <button
          v-if="tab === 'clients'"
          class="rounded-md bg-primary px-3.5 py-1.5 text-[13px] font-medium text-white transition-colors hover:bg-primary-strong"
          @click="showCreate = true"
        >
          Ajouter un client
        </button>
      </div>
    </div>

    <!-- Clients -->
    <div v-if="tab === 'clients'" class="overflow-x-auto rounded-lg border border-ink/8 bg-surface">
      <table class="w-full min-w-[640px] text-[13.5px]">
        <thead>
          <tr class="border-b border-ink/8 text-left text-[11.5px] uppercase tracking-[0.06em] text-ink/45">
            <th class="px-[18px] py-3 font-medium">Client</th>
            <th class="px-[18px] py-3 font-medium">Secteur</th>
            <th class="px-[18px] py-3 font-medium">Ville</th>
            <th class="px-[18px] py-3 font-medium">Statut</th>
            <th class="px-4 py-3 text-right font-medium">Contacts</th>
            <th class="px-4 py-3 text-right font-medium">Pipeline pondéré</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="client in filteredClients"
            :key="client.id"
            class="cursor-pointer border-b border-ink/5 transition-colors last:border-0 hover:bg-primary-soft/40"
            @click="openClient(client.id)"
          >
            <td class="px-[18px] py-[11px] font-medium">{{ client.name }}</td>
            <td class="px-[18px] py-[11px] text-ink/60">{{ client.sector }}</td>
            <td class="px-[18px] py-[11px] text-ink/60">{{ client.city }}</td>
            <td class="px-[18px] py-[11px]"><StatusBadge :status="client.status" /></td>
            <td class="tnum px-[18px] py-[11px] text-right text-ink/60">{{ client.contactCount }}</td>
            <td class="tnum px-[18px] py-[11px] text-right">{{ client.weightedPipeline > 0 ? euro(client.weightedPipeline) : '—' }}</td>
          </tr>
        </tbody>
      </table>
      <p v-if="filteredClients.length === 0" class="px-4 py-10 text-center text-[13px] text-ink/45">
        Aucun client ne correspond à « {{ search }} ». Modifiez la recherche ou ajoutez un client.
      </p>
    </div>

    <!-- Opportunités -->
    <div v-else class="overflow-x-auto rounded-lg border border-ink/8 bg-surface">
      <table class="w-full min-w-[640px] text-[13.5px]">
        <thead>
          <tr class="border-b border-ink/8 text-left text-[11.5px] uppercase tracking-[0.06em] text-ink/45">
            <th class="px-[18px] py-3 font-medium">Opportunité</th>
            <th class="px-[18px] py-3 font-medium">Client</th>
            <th class="px-[18px] py-3 font-medium">Practice</th>
            <th class="px-4 py-3 text-right font-medium">Montant HT</th>
            <th class="px-4 py-3 text-right font-medium">Probabilité</th>
            <th class="px-[18px] py-3 font-medium">Étape</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="opp in opportunities" :key="opp.id" class="border-b border-ink/5 last:border-0">
            <td class="px-[18px] py-[11px] font-medium">{{ opp.title }}</td>
            <td class="px-[18px] py-[11px] text-ink/60">{{ opp.clientName }}</td>
            <td class="px-[18px] py-[11px] text-ink/60">{{ opp.practiceLabel }}</td>
            <td class="tnum px-[18px] py-[11px] text-right">{{ euro(opp.amount) }}</td>
            <td class="tnum px-[18px] py-[11px] text-right text-ink/60">{{ opp.probability }} %</td>
            <td class="px-[18px] py-[11px]">
              <select
                :value="opp.stage"
                class="rounded-md border border-ink/12 bg-surface px-2 py-1 text-[12.5px]"
                @change="changeStage(opp, ($event.target as HTMLSelectElement).value)"
              >
                <option v-for="stage in stages" :key="stage" :value="stage">{{ stage }}</option>
              </select>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Création client -->
    <div v-if="showCreate" class="fixed inset-0 z-20 flex items-center justify-center bg-shell/40" @click.self="showCreate = false">
      <form class="w-full max-w-sm rounded-lg bg-surface p-6 shadow-2xl" @submit.prevent="createClient">
        <h2 class="mb-5 font-display text-lg font-semibold tracking-tight">Ajouter un client</h2>
        <div class="space-y-3.5">
          <div v-for="f in textFields" :key="f.key">
            <label :for="f.key" class="mb-1 block text-[13px] font-medium text-ink/75">{{ f.label }}</label>
            <input
              :id="f.key"
              v-model="form[f.key]"
              class="w-full rounded-md border border-ink/12 px-3 py-2 text-[13.5px] focus:border-primary"
            />
            <p v-if="formErrors[f.key]" class="mt-1 text-[12.5px] text-alert">{{ formErrors[f.key] }}</p>
          </div>
          <div>
            <label for="status" class="mb-1 block text-[13px] font-medium text-ink/75">Statut</label>
            <select id="status" v-model="form.status" class="w-full rounded-md border border-ink/12 bg-surface px-3 py-2 text-[13.5px]">
              <option value="prospect">Prospect</option>
              <option value="actif">Actif</option>
              <option value="inactif">Inactif</option>
            </select>
          </div>
        </div>
        <div class="mt-6 flex justify-end gap-2.5">
          <button type="button" class="rounded-md px-3.5 py-2 text-[13px] text-ink/60 hover:text-ink" @click="showCreate = false">Annuler</button>
          <button type="submit" class="rounded-md bg-primary px-3.5 py-2 text-[13px] font-medium text-white hover:bg-primary-strong">Créer le client</button>
        </div>
      </form>
    </div>
  </div>
</template>
