<script setup lang="ts">
import { accentKey, accents, closePreferences, darkMode, preferencesOpen, setAccent, setDark } from '@/lib/theme'
</script>

<template>
  <div
    v-if="preferencesOpen"
    class="fixed inset-0 z-40 flex items-center justify-center bg-shell/40 backdrop-blur-[2px]"
    @click.self="closePreferences"
  >
    <div class="prefs-modal w-full max-w-sm overflow-hidden rounded-xl bg-surface shadow-[0_24px_60px_rgba(24,29,39,.35)]" role="dialog" aria-label="Mes préférences">
      <div class="flex items-center justify-between border-b border-ink/8 px-[18px] py-3.5">
        <h2 class="font-display text-[15px] font-semibold tracking-tight">Mes préférences</h2>
        <button class="px-1.5 py-0.5 text-[15px] text-ink/40 hover:text-ink" aria-label="Fermer" @click="closePreferences">✕</button>
      </div>

      <div class="space-y-6 px-[18px] py-5">
        <!-- Thème -->
        <div>
          <p class="mb-2.5 text-[11px] font-medium uppercase tracking-[0.08em] text-ink/45">Thème</p>
          <div class="flex gap-1 rounded-md border border-ink/10 p-1">
            <button
              class="flex-1 rounded px-3 py-1.5 text-[13px] font-medium transition-colors"
              :class="!darkMode ? 'bg-primary text-white' : 'text-ink/60 hover:text-ink'"
              @click="setDark(false)"
            >
              ☾ Clair
            </button>
            <button
              class="flex-1 rounded px-3 py-1.5 text-[13px] font-medium transition-colors"
              :class="darkMode ? 'bg-primary text-white' : 'text-ink/60 hover:text-ink'"
              @click="setDark(true)"
            >
              ☀ Sombre
            </button>
          </div>
        </div>

        <!-- Accent -->
        <div>
          <p class="mb-2.5 text-[11px] font-medium uppercase tracking-[0.08em] text-ink/45">Couleur d'accent</p>
          <div class="flex items-center gap-3">
            <button
              v-for="accent in accents"
              :key="accent.key"
              class="flex h-9 w-9 items-center justify-center rounded-full transition-transform hover:scale-110"
              :class="accentKey === accent.key ? 'ring-2 ring-offset-2 ring-offset-surface' : ''"
              :style="{ background: accent.primary, '--tw-ring-color': accent.primary }"
              :aria-label="accent.label"
              :aria-pressed="accentKey === accent.key"
              @click="setAccent(accent.key)"
            >
              <span v-if="accentKey === accent.key" class="text-[13px] font-bold text-white">✓</span>
            </button>
          </div>
          <p class="mt-2.5 text-[12px] text-ink/45">{{ accents.find((a) => a.key === accentKey)?.label }} · appliqué instantanément</p>
        </div>

        <p class="border-t border-ink/6 pt-3 text-[11.5px] text-ink/40">
          Vos préférences sont enregistrées dans ce navigateur (localStorage).
        </p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.prefs-modal {
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
