<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { login } from '@/lib/session'
import { ApiError } from '@/lib/api'
import SynapseMark from '@/components/SynapseMark.vue'

const router = useRouter()

const email = ref('direction@synapse.demo')
const password = ref('')
const error = ref('')
const loading = ref(false)

const demoAccounts = [
  { email: 'direction@synapse.demo', label: 'Direction' },
  { email: 'staffing@synapse.demo', label: 'Staffing' },
  { email: 'commerce@synapse.demo', label: 'Commerce' },
]

async function submit() {
  error.value = ''
  loading.value = true
  try {
    await login(email.value, password.value)
    await router.push('/dashboard')
  } catch (e) {
    error.value = e instanceof ApiError && e.status === 401
      ? 'Identifiants invalides. Vérifiez l\'adresse e-mail et le mot de passe.'
      : 'Le serveur ne répond pas. Réessayez dans un instant.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen">
    <!-- Panneau de marque -->
    <div class="hidden flex-col justify-between bg-ink p-10 text-white lg:flex lg:w-[44%]">
      <div class="flex items-center gap-2.5">
        <SynapseMark :size="30" class="text-white" />
        <span class="font-display text-lg font-semibold tracking-tight">Synapse</span>
      </div>

      <div>
        <SynapseMark :size="120" animated class="mb-8 text-white/80" />
        <h1 class="font-display text-3xl font-semibold leading-snug tracking-tight">
          Le système nerveux<br />opérationnel du cabinet.
        </h1>
        <p class="mt-4 max-w-sm text-[14px] leading-relaxed text-white/55">
          CRM, staffing, projets et facturation dans un seul outil,
          piloté à la voix ou au clavier par l'assistant Synapse.
        </p>
      </div>

      <p class="text-[12px] text-white/35">
        Démonstration technique — données fictives · non affilié à Synetis
      </p>
    </div>

    <!-- Formulaire -->
    <div class="flex flex-1 items-center justify-center p-6">
      <div class="w-full max-w-sm">
        <div class="mb-8 flex items-center gap-2.5 lg:hidden">
          <SynapseMark :size="28" class="text-ink" />
          <span class="font-display text-lg font-semibold tracking-tight">Synapse</span>
        </div>

        <h2 class="font-display text-2xl font-semibold tracking-tight">Connexion</h2>
        <p class="mt-1.5 text-[13.5px] text-ink/55">Accédez à l'espace interne du cabinet.</p>

        <form class="mt-8 space-y-4" @submit.prevent="submit">
          <div>
            <label for="email" class="mb-1.5 block text-[13px] font-medium text-ink/75">Adresse e-mail</label>
            <input
              id="email"
              v-model="email"
              type="email"
              required
              autocomplete="username"
              class="w-full rounded-md border border-ink/15 bg-white px-3 py-2 text-[14px] transition-colors focus:border-primary"
            />
          </div>
          <div>
            <label for="password" class="mb-1.5 block text-[13px] font-medium text-ink/75">Mot de passe</label>
            <input
              id="password"
              v-model="password"
              type="password"
              required
              autocomplete="current-password"
              class="w-full rounded-md border border-ink/15 bg-white px-3 py-2 text-[14px] transition-colors focus:border-primary"
            />
          </div>

          <p v-if="error" class="rounded-md bg-alert/8 px-3 py-2 text-[13px] text-alert">{{ error }}</p>

          <button
            type="submit"
            :disabled="loading"
            class="w-full rounded-md bg-primary py-2.5 text-[14px] font-medium text-white transition-colors hover:bg-primary-strong disabled:opacity-60"
          >
            {{ loading ? 'Connexion…' : 'Se connecter' }}
          </button>
        </form>

        <div class="mt-8 rounded-md border border-ink/10 bg-white p-4">
          <p class="text-[12px] font-medium uppercase tracking-[0.08em] text-ink/45">Comptes de démonstration</p>
          <div class="mt-2.5 flex flex-wrap gap-2">
            <button
              v-for="account in demoAccounts"
              :key="account.email"
              type="button"
              class="rounded-md border border-ink/12 px-2.5 py-1 text-[12.5px] text-ink/70 transition-colors hover:border-primary hover:text-primary"
              @click="email = account.email"
            >
              {{ account.label }}
            </button>
          </div>
          <p class="tnum mt-2.5 font-mono text-[12px] text-ink/50">Mot de passe : Synapse!2026</p>
        </div>
      </div>
    </div>
  </div>
</template>
