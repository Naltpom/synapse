<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  activeScenario,
  assistantOpen,
  closeAssistant,
  mockEngine,
  scenarioFor,
  suggestions,
} from '@/lib/assistant'
import SynapseMark from './SynapseMark.vue'

const router = useRouter()
const prompt = ref('')
const noMatch = ref(false)

function pick(key: string) {
  activeScenario.value = scenarioFor(key)
  noMatch.value = false
}

function submit() {
  if (!prompt.value.trim()) return
  const scenario = mockEngine.run(prompt.value)
  if (scenario) {
    activeScenario.value = scenario
    noMatch.value = false
    prompt.value = ''
  } else {
    noMatch.value = true
  }
}

function reset() {
  activeScenario.value = null
}

function goTarget() {
  const target = activeScenario.value?.target
  closeAssistant()
  if (target) router.push({ name: target })
}
</script>

<template>
  <div
    v-if="assistantOpen"
    class="fixed inset-0 z-30 flex justify-center bg-ink/50 backdrop-blur-[2px]"
    @click.self="closeAssistant"
  >
    <div class="assistant-modal mt-[90px] h-fit w-[680px] max-w-[92vw] overflow-hidden rounded-xl bg-white shadow-[0_24px_60px_rgba(24,29,39,.35)]" role="dialog" aria-label="Assistant Synapse">
      <!-- Header -->
      <div class="flex items-center gap-2.5 border-b border-ink/8 px-[18px] py-3.5">
        <SynapseMark :size="20" animated class="text-primary" />
        <h2 class="font-display text-[15px] font-semibold tracking-tight">Assistant Synapse</h2>
        <span class="rounded px-2 py-0.5 text-[11px] text-ink/45" style="background: rgba(0, 72, 254, 0.06)">
          connecté à l'API via MCP · actions journalisées
        </span>
        <button class="ml-auto px-1.5 py-0.5 text-[15px] text-ink/40 hover:text-ink" aria-label="Fermer" @click="closeAssistant">✕</button>
      </div>

      <!-- Input -->
      <div class="flex items-center gap-2.5 border-b border-ink/8 px-[18px] py-4">
        <input
          v-model="prompt"
          placeholder="Décrivez ce que vous voulez faire — l'assistant s'occupe des clics…"
          class="flex-1 bg-transparent py-1 text-[14.5px] outline-none"
          @keydown.enter="submit"
        />
        <button class="flex flex-none items-center gap-1.5 rounded-full border border-ink/15 px-3 py-[5px] text-[12px] text-ink/60 transition-colors hover:border-primary hover:text-primary">
          <span class="h-[7px] w-[7px] rounded-full bg-alert" />
          Voix
        </button>
      </div>

      <!-- Idle : suggestions -->
      <div v-if="!activeScenario" class="px-[18px] pt-4 pb-5">
        <p class="mb-2.5 text-[11px] font-medium uppercase tracking-[0.08em] text-ink/45">Essayez par exemple</p>
        <div class="flex flex-col gap-2">
          <button
            v-for="s in suggestions"
            :key="s.key"
            class="flex items-center gap-2.5 rounded-lg border border-ink/10 px-3.5 py-2.5 text-left text-[13.5px] transition-colors hover:border-primary hover:bg-primary-soft/35"
            @click="pick(s.key)"
          >
            <span class="flex-none rounded bg-primary/8 px-1.5 py-0.5 font-mono text-[10px] font-medium text-primary-strong">{{ s.domain }}</span>
            {{ s.text }}
          </button>
        </div>
        <p v-if="noMatch" class="mt-3 rounded-md bg-alert/8 px-3 py-2 text-[12.5px] text-alert">
          Cette démo ne couvre que les quatre exemples ci-dessus — le moteur complet arrive avec le backend LLM.
        </p>
        <p class="mt-4 text-[12px] text-ink/45">
          Chaque action passe par l'API avec vos droits — rien ne s'exécute sans confirmation, tout est tracé au journal d'audit.
        </p>
      </div>

      <!-- Scénario -->
      <div v-else class="flex max-h-[460px] flex-col gap-3.5 overflow-y-auto px-[18px] pt-4 pb-5">
        <!-- Message utilisateur -->
        <div class="max-w-[80%] self-end rounded-[10px_10px_2px_10px] bg-primary px-3.5 py-[9px] text-[13.5px] text-white">
          {{ activeScenario.user }}
        </div>

        <!-- Trace MCP -->
        <div class="rounded-lg bg-ink px-3.5 py-3 text-white">
          <p class="mb-2 text-[10px] uppercase tracking-[0.12em] text-white/40">Outils MCP appelés</p>
          <div class="flex flex-col gap-[5px] font-mono text-[12px]">
            <div v-for="step in activeScenario.steps" :key="step.tool" class="flex flex-wrap gap-2.5">
              <span class="flex-none text-[#6b9aff]">→</span>
              <span class="text-[#7ee2a8]">{{ step.tool }}</span>
              <span class="text-white/55">{{ step.args }}</span>
              <span class="ml-auto text-white/40">{{ step.result }}</span>
            </div>
          </div>
        </div>

        <!-- Résumé assistant -->
        <div class="flex max-w-[92%] gap-2.5">
          <SynapseMark :size="18" class="mt-0.5 flex-none text-primary" />
          <div class="rounded-[10px_10px_10px_2px] bg-cloud px-3.5 py-2.5 text-[13.5px] leading-[1.55]">
            {{ activeScenario.summary }}
          </div>
        </div>

        <!-- Carte résultat -->
        <div class="flex items-center gap-3 rounded-lg border border-ink/10 px-3.5 py-3">
          <span class="h-2 w-2 flex-none rounded-full bg-warn" />
          <div class="min-w-0 flex-1">
            <p class="text-[13px] font-medium">{{ activeScenario.resultTitle }}</p>
            <p class="mt-px text-[12px] text-ink/55">{{ activeScenario.resultSub }}</p>
          </div>
          <button
            class="flex-none rounded-[5px] border border-primary/30 px-2.5 py-1 text-[12px] font-medium text-primary transition-colors hover:bg-primary-soft"
            @click="goTarget"
          >
            {{ activeScenario.resultAction }}
          </button>
        </div>

        <!-- Confirmation -->
        <div class="flex items-center gap-2">
          <button class="rounded-[5px] bg-primary px-3.5 py-1.5 text-[12.5px] font-medium text-white transition-colors hover:bg-primary-strong" @click="goTarget">
            Confirmer
          </button>
          <button class="px-2.5 py-1.5 text-[12.5px] font-medium text-ink/60 hover:text-ink" @click="reset">
            Annuler
          </button>
          <span class="tnum ml-auto font-mono text-[11px] text-ink/40">audit: pending_confirmation</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.assistant-modal {
  animation: fadeUp 0.18s ease-out;
}
@keyframes fadeUp {
  from {
    opacity: 0;
    transform: translateY(6px);
  }
  to {
    opacity: 1;
    transform: none;
  }
}
</style>
