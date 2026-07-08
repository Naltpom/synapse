<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { currentUser, isAdmin, logout } from '@/lib/session'
import { navCounters, refreshNavCounters } from '@/lib/nav'
import { closeAssistant, openAssistant, toggleAssistant } from '@/lib/assistant'
import SynapseMark from './SynapseMark.vue'
import AssistantPalette from './AssistantPalette.vue'

const route = useRoute()

function onKeydown(e: KeyboardEvent) {
  if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
    e.preventDefault()
    toggleAssistant()
  }
  if (e.key === 'Escape') closeAssistant()
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))

interface NavItem {
  to: string
  label: string
  badge?: number
  badgeClass?: string
}

const navPilotage = computed<NavItem[]>(() => [
  { to: '/dashboard', label: 'Vue d\'ensemble' },
  ...(isAdmin.value ? [{ to: '/audit', label: 'Journal d\'audit' }] : []),
])

const navOperations = computed<NavItem[]>(() => [
  { to: '/crm', label: 'CRM' },
  {
    to: '/staffing',
    label: 'Staffing',
    badge: navCounters.value?.staffingBench || undefined,
    badgeClass: 'bg-[rgba(180,83,9,.35)] text-[#ffc38a]',
  },
  { to: '/projets', label: 'Projets' },
  {
    to: '/facturation',
    label: 'Facturation',
    badge: navCounters.value?.billingOverdue || undefined,
    badgeClass: 'bg-[rgba(237,28,36,.3)] text-[#ff9ba0]',
  },
  {
    to: '/conges',
    label: 'Congés',
    badge: navCounters.value?.hrPending || undefined,
    badgeClass: 'bg-white/10 text-white/60',
  },
])

const today = computed(() => {
  const now = new Date()
  const label = new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(now)
  // Numéro de semaine ISO 8601.
  const d = new Date(Date.UTC(now.getFullYear(), now.getMonth(), now.getDate()))
  d.setUTCDate(d.getUTCDate() + 4 - (d.getUTCDay() || 7))
  const week = Math.ceil(((d.getTime() - Date.UTC(d.getUTCFullYear(), 0, 1)) / 86400000 + 1) / 7)
  return `${label.charAt(0).toUpperCase()}${label.slice(1)} · S${week}`
})

const initials = (name: string) =>
  name.split(' ').map((part) => part[0]).join('').slice(0, 2).toUpperCase()

onMounted(refreshNavCounters)
</script>

<template>
  <div class="flex min-h-screen">
    <aside class="sticky top-0 flex h-screen w-56 flex-none flex-col bg-ink text-white">
      <div class="flex items-center gap-2.5 px-5 pt-[22px] pb-[26px]">
        <SynapseMark :size="30" class="text-white" />
        <div>
          <p class="font-display text-[17px] font-semibold leading-none tracking-tight">Synapse</p>
          <p class="mt-1 text-[10px] uppercase tracking-[0.14em] text-white/40">ERP interne</p>
        </div>
      </div>

      <nav class="flex flex-1 flex-col gap-0.5 px-3" aria-label="Navigation principale">
        <p class="px-3 pt-1 pb-1.5 text-[10px] uppercase tracking-[0.14em] text-white/35">Pilotage</p>
        <router-link
          v-for="item in navPilotage"
          :key="item.to"
          :to="item.to"
          class="flex items-center justify-between rounded-md px-3 py-2 text-[13.5px] text-white/65 transition-colors hover:bg-white/5 hover:text-white"
          :class="{ 'bg-primary! text-white! font-medium': route.path.startsWith(item.to) }"
        >
          {{ item.label }}
        </router-link>

        <p class="px-3 pt-4 pb-1.5 text-[10px] uppercase tracking-[0.14em] text-white/35">Opérations</p>
        <router-link
          v-for="item in navOperations"
          :key="item.to"
          :to="item.to"
          class="flex items-center justify-between rounded-md px-3 py-2 text-[13.5px] text-white/65 transition-colors hover:bg-white/5 hover:text-white"
          :class="{ 'bg-primary! text-white! font-medium': route.path.startsWith(item.to) }"
        >
          {{ item.label }}
          <span
            v-if="item.badge"
            class="rounded-[8px] px-[7px] py-px font-mono text-[10.5px] font-medium"
            :class="item.badgeClass"
          >
            {{ item.badge }}
          </span>
        </router-link>
      </nav>

      <div class="border-t border-white/10 p-4">
        <div class="flex items-center gap-[11px]">
          <span class="flex h-[34px] w-[34px] shrink-0 items-center justify-center rounded-full bg-primary font-display text-[11px] font-semibold">
            {{ currentUser ? initials(currentUser.fullName) : '·' }}
          </span>
          <div class="min-w-0 flex-1">
            <p class="truncate text-[13px] font-medium">{{ currentUser?.fullName }}</p>
            <p class="truncate text-[11px] text-white/45">{{ currentUser?.jobTitle }}</p>
          </div>
        </div>
        <router-link
          to="/securite"
          class="mt-2.5 block text-center text-[12px] text-white/45 transition-colors hover:text-white"
        >
          Ma sécurité
        </router-link>
        <button
          class="mt-2 w-full rounded-md border border-white/15 py-1.5 text-[12.5px] text-white/70 transition-colors hover:bg-white/5 hover:text-white"
          @click="logout"
        >
          Se déconnecter
        </button>
      </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
      <header class="bg-ink text-white">
        <div class="flex items-center justify-between gap-5 px-8 py-5">
          <div>
            <h1 class="font-display text-xl font-semibold tracking-tight">{{ route.meta.title }}</h1>
            <p class="mt-0.5 text-[12.5px] text-white/45">{{ today }}</p>
          </div>
          <button
            class="flex w-80 items-center gap-[9px] rounded-lg border border-white/12 bg-white/7 px-3 py-[7px] text-[12.5px] text-white/50 transition-colors hover:border-[#6b9aff]/60 hover:text-white/75"
            @click="openAssistant"
          >
            <svg width="14" height="14" viewBox="0 0 32 32" fill="none" aria-hidden="true">
              <path d="M8 24 L16 8 M16 8 L26 20 M8 24 L26 20" stroke="#6b9aff" stroke-width="2.4" opacity="0.6" />
              <circle cx="8" cy="24" r="4.4" fill="#6b9aff" />
              <circle cx="16" cy="8" r="5.2" fill="#0048fe" />
              <circle cx="26" cy="20" r="3.8" fill="#6b9aff" />
            </svg>
            Demander à Synapse…
            <kbd class="ml-auto rounded border border-white/20 px-[5px] py-px font-mono text-[10px] font-medium">⌘K</kbd>
          </button>
        </div>
        <!-- Le dashboard téléporte son héro ici (métriques + sparklines). -->
        <div id="hero-outlet"></div>
      </header>

      <main class="flex-1 px-8 py-6">
        <router-view />
      </main>
    </div>

    <AssistantPalette />
  </div>
</template>
