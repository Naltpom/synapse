<script setup lang="ts">
const props = defineProps<{ status: string }>()

/** Statuts métier → libellé + tonalité. Couleur jamais seule : le libellé porte l'information. */
const registry: Record<string, { label: string; tone: 'ok' | 'warn' | 'alert' | 'neutral' | 'info' }> = {
  // Clients
  prospect: { label: 'Prospect', tone: 'info' },
  actif: { label: 'Actif', tone: 'ok' },
  inactif: { label: 'Inactif', tone: 'neutral' },
  // Opportunités
  qualification: { label: 'Qualification', tone: 'neutral' },
  proposition: { label: 'Proposition', tone: 'info' },
  negociation: { label: 'Négociation', tone: 'warn' },
  gagnee: { label: 'Gagnée', tone: 'ok' },
  perdue: { label: 'Perdue', tone: 'neutral' },
  // Missions
  a_venir: { label: 'À venir', tone: 'info' },
  en_cours: { label: 'En cours', tone: 'ok' },
  terminee: { label: 'Terminée', tone: 'neutral' },
  // Projets
  cadrage: { label: 'Cadrage', tone: 'info' },
  recette: { label: 'Recette', tone: 'warn' },
  clos: { label: 'Clos', tone: 'neutral' },
  vert: { label: 'Vert', tone: 'ok' },
  orange: { label: 'Orange', tone: 'warn' },
  rouge: { label: 'Rouge', tone: 'alert' },
  // Factures
  brouillon: { label: 'Brouillon', tone: 'neutral' },
  envoyee: { label: 'Envoyée', tone: 'info' },
  payee: { label: 'Payée', tone: 'ok' },
  en_retard: { label: 'En retard', tone: 'alert' },
  // CRA
  draft: { label: 'Brouillon', tone: 'neutral' },
  submitted: { label: 'Soumise', tone: 'warn' },
  validated: { label: 'Validée', tone: 'ok' },
}

const entry = registry[props.status] ?? { label: props.status, tone: 'neutral' as const }

const tones = {
  ok: 'bg-ok/10 text-ok',
  warn: 'bg-warn/10 text-warn',
  alert: 'bg-alert/10 text-alert',
  info: 'bg-primary/8 text-primary-strong',
  neutral: 'bg-ink/6 text-ink/60',
}
</script>

<template>
  <span
    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[12px] font-medium"
    :class="tones[entry.tone]"
  >
    <span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true" />
    {{ entry.label }}
  </span>
</template>
