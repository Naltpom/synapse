<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api } from '@/lib/api'
import Skeleton from '@/components/Skeleton.vue'

interface MeSecurity {
  session: { email: string; fullName: string; jobTitle: string; roles: string[] }
  logins: { id: number; occurredAt: string; action: 'login' | 'login_failure'; ip: string | null }[]
}

const data = ref<MeSecurity | null>(null)

onMounted(async () => {
  data.value = await api.get<MeSecurity>('/api/me/security')
})

const timestamp = (iso: string) =>
  new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso))

const roleLabels: Record<string, string> = {
  ROLE_ADMIN: 'Direction',
  ROLE_MANAGER: 'Manager',
  ROLE_USER: 'Utilisateur',
}
</script>

<template>
  <div v-if="data" class="grid items-start gap-[22px] xl:grid-cols-[1fr_380px]">
    <!-- Historique de connexion -->
    <section class="overflow-hidden rounded-lg border border-ink/8 bg-surface">
      <div class="border-b border-ink/7 px-[18px] py-4">
        <h2 class="font-display text-[15px] font-semibold tracking-tight">Mes dernières connexions</h2>
        <p class="mt-0.5 text-[12.5px] text-ink/50">Extraites du journal d'audit — signalez toute connexion que vous ne reconnaissez pas.</p>
      </div>
      <div
        v-for="entry in data.logins"
        :key="entry.id"
        class="flex items-center gap-3 border-b border-ink/6 px-[18px] py-3 last:border-0"
      >
        <span class="h-2 w-2 flex-none rounded-full" :class="entry.action === 'login' ? 'bg-ok' : 'bg-alert'" />
        <span class="text-[13px] font-medium" :class="{ 'text-alert': entry.action === 'login_failure' }">
          {{ entry.action === 'login' ? 'Connexion réussie' : 'Tentative échouée' }}
        </span>
        <span class="tnum text-[12.5px] text-ink/55">{{ timestamp(entry.occurredAt) }}</span>
        <span v-if="entry.ip" class="ml-auto font-mono text-[12px] text-ink/45">{{ entry.ip }}</span>
      </div>
      <p v-if="data.logins.length === 0" class="px-[18px] py-8 text-center text-[13px] text-ink/45">
        Aucune connexion enregistrée pour l'instant.
      </p>
    </section>

    <div class="flex flex-col gap-[22px]">
      <!-- Session actuelle -->
      <section class="rounded-lg border border-ink/8 bg-surface p-[18px]">
        <h2 class="font-display text-[15px] font-semibold tracking-tight">Session actuelle</h2>
        <dl class="mt-3 space-y-2 text-[13px]">
          <div class="flex justify-between gap-3">
            <dt class="text-ink/50">Compte</dt>
            <dd class="truncate font-mono text-[12.5px]">{{ data.session.email }}</dd>
          </div>
          <div class="flex justify-between gap-3">
            <dt class="text-ink/50">Fonction</dt>
            <dd>{{ data.session.jobTitle }}</dd>
          </div>
          <div class="flex justify-between gap-3">
            <dt class="text-ink/50">Rôles</dt>
            <dd class="flex flex-wrap justify-end gap-1.5">
              <span
                v-for="role in data.session.roles"
                :key="role"
                class="rounded bg-primary/8 px-1.5 py-0.5 font-mono text-[10.5px] font-medium text-primary-strong"
              >
                {{ roleLabels[role] ?? role }}
              </span>
            </dd>
          </div>
        </dl>
        <p class="mt-4 border-t border-ink/6 pt-3 text-[12px] text-ink/45">
          Session protégée : cookie HttpOnly · SameSite · anti brute-force 5 tentatives / 15 min.
        </p>
      </section>

      <!-- MFA / SSO teaser -->
      <section class="rounded-lg border border-dashed border-ink/15 bg-surface p-[18px]">
        <div class="flex items-center justify-between">
          <h2 class="font-display text-[15px] font-semibold tracking-tight">Authentification renforcée</h2>
          <span class="rounded-full bg-ink/6 px-2.5 py-0.5 text-[11px] font-medium text-ink/55">Bientôt</span>
        </div>
        <p class="mt-2 text-[12.5px] leading-relaxed text-ink/55">
          SSO d'entreprise (OIDC) et MFA arrivent avec l'intégration au socle IAM du cabinet —
          l'architecture de session actuelle est prête à déléguer l'authentification.
        </p>
      </section>
    </div>
  </div>

  <div v-else class="rounded-lg border border-ink/8 bg-surface p-6"><Skeleton :lines="6" /></div>
</template>
