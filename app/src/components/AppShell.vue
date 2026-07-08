<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { currentUser, isAdmin, logout } from '@/lib/session'
import SynapseMark from './SynapseMark.vue'

const route = useRoute()

const nav = computed(() => [
  { to: '/dashboard', label: 'Vue d\'ensemble' },
  { to: '/crm', label: 'CRM' },
  { to: '/staffing', label: 'Staffing' },
  { to: '/projets', label: 'Projets' },
  { to: '/facturation', label: 'Facturation' },
  // Le journal d'audit est réservé à la direction (ROLE_ADMIN).
  ...(isAdmin.value ? [{ to: '/audit', label: 'Journal d\'audit' }] : []),
])

const initials = (name: string) =>
  name.split(' ').map((part) => part[0]).join('').slice(0, 2).toUpperCase()
</script>

<template>
  <div class="flex min-h-screen">
    <aside class="fixed inset-y-0 left-0 flex w-60 flex-col bg-ink text-white">
      <div class="flex items-center gap-2.5 px-5 pt-6 pb-8">
        <SynapseMark :size="30" class="text-white" />
        <div>
          <p class="font-display text-lg font-semibold leading-none tracking-tight">Synapse</p>
          <p class="mt-1 text-[11px] uppercase tracking-[0.14em] text-white/40">ERP · démo</p>
        </div>
      </div>

      <nav class="flex flex-1 flex-col gap-1 px-3" aria-label="Navigation principale">
        <router-link
          v-for="item in nav"
          :key="item.to"
          :to="item.to"
          class="rounded-md px-3 py-2 text-[13.5px] text-white/65 transition-colors hover:bg-white/5 hover:text-white"
          :class="{ 'bg-primary! text-white! font-medium': route.path.startsWith(item.to) }"
        >
          {{ item.label }}
        </router-link>
      </nav>

      <div class="border-t border-white/10 p-4">
        <div class="flex items-center gap-3">
          <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary font-display text-xs font-semibold">
            {{ currentUser ? initials(currentUser.fullName) : '·' }}
          </span>
          <div class="min-w-0 flex-1">
            <p class="truncate text-[13px] font-medium">{{ currentUser?.fullName }}</p>
            <p class="truncate text-[11.5px] text-white/45">{{ currentUser?.jobTitle }}</p>
          </div>
        </div>
        <button
          class="mt-3 w-full rounded-md border border-white/15 px-3 py-1.5 text-[12.5px] text-white/70 transition-colors hover:bg-white/5 hover:text-white"
          @click="logout"
        >
          Se déconnecter
        </button>
      </div>
    </aside>

    <div class="ml-60 flex-1">
      <header class="sticky top-0 z-10 border-b border-ink/8 bg-cloud/85 px-8 py-4 backdrop-blur">
        <h1 class="font-display text-xl font-semibold tracking-tight">{{ route.meta.title }}</h1>
      </header>
      <main class="px-8 py-6">
        <router-view />
      </main>
    </div>
  </div>
</template>
