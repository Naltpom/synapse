import { ref } from 'vue'

/**
 * État global de l'assistant Synapse (palette ⌘K).
 *
 * Cette itération embarque un moteur mocké (scénarios rejoués côté front).
 * L'architecture cible remplace MockEngine par un backend LLM + serveur MCP
 * exposant les endpoints de l'API avec les droits de session de l'utilisateur —
 * l'interface AssistantEngine est le seul point de branchement.
 */

export interface McpStep {
  tool: string
  args: string
  result: string
}

export interface Scenario {
  user: string
  steps: McpStep[]
  summary: string
  resultTitle: string
  resultSub: string
  resultAction: string
  /** Nom de route vue-router vers l'écran concerné. */
  target: string
}

export interface Suggestion {
  domain: string
  text: string
  key: string
}

export interface AssistantEngine {
  run(prompt: string): Scenario | null
}

export const assistantOpen = ref(false)
export const activeScenario = ref<Scenario | null>(null)

export function openAssistant(): void {
  assistantOpen.value = true
  activeScenario.value = null
}

export function closeAssistant(): void {
  assistantOpen.value = false
  activeScenario.value = null
}

export function toggleAssistant(): void {
  assistantOpen.value ? closeAssistant() : openAssistant()
}

export const suggestions: Suggestion[] = [
  { domain: 'RH', text: 'Mets-moi en congé demain et lundi prochain', key: 'leave' },
  { domain: 'Facturation', text: 'Relance les 3 factures en retard avec un mail poli', key: 'invoices' },
  { domain: 'Staffing', text: 'Qui est dispo en Audit SSI pour un pentest de 10 jours fin juillet ?', key: 'staffing' },
  { domain: 'CRM', text: 'Crée une opportunité audit cloud 45 k€ pour Nexalog', key: 'crm' },
]

/** Scénarios de démonstration — contenu repris tel quel de la maquette validée. */
const scenarios: Record<string, Scenario> = {
  leave: {
    user: 'Mets-moi en congé demain et lundi prochain',
    steps: [
      { tool: 'staffing.get_allocations', args: '{consultant:"n.provost", from:"2026-07-09"}', result: '✓ aucune mission bloquante' },
      { tool: 'hr.create_leave_request', args: '{dates:["09/07","13/07"]}', result: '✓ LeaveRequest#31' },
      { tool: 'notify.manager', args: '{to:"r.fournier", type:"approval"}', result: '✓ notifié' },
    ],
    summary:
      'C’est fait : demande de congé posée pour jeudi 9 et lundi 13 juillet (2 jours). Vous n’avez aucune mission staffée ces jours-là. R. Fournier a été notifié pour validation — le calendrier staffing affiche déjà ces jours en attente.',
    resultTitle: 'Demande de congé #31 — en attente de validation',
    resultSub: 'Jeudi 9 juil. + lundi 13 juil. · valideur : R. Fournier',
    resultAction: 'Voir le calendrier',
    target: 'leave',
  },
  invoices: {
    user: 'Relance les 3 factures en retard avec un mail poli',
    steps: [
      { tool: 'billing.list_invoices', args: '{status:"en_retard"}', result: '✓ 3 factures · 86 400 €' },
      { tool: 'crm.get_contacts', args: '{role:"comptabilité"}', result: '✓ 3 contacts' },
      { tool: 'mail.draft_batch', args: '{template:"relance_j+15", tone:"courtois"}', result: '✓ 3 brouillons' },
    ],
    summary:
      '3 brouillons de relance préparés (Banque Hexagone 38,4 k€, Sofratel 26 k€, Ville de Clairmont 22 k€), adressés aux contacts comptabilité, ton courtois avec rappel des échéances. Rien n’est parti : vous validez avant envoi.',
    resultTitle: '3 relances prêtes à envoyer',
    resultSub: 'Brouillons visibles dans Facturation · envoi après confirmation',
    resultAction: 'Voir les factures',
    target: 'billing',
  },
  staffing: {
    user: 'Qui est dispo en Audit SSI pour un pentest de 10 jours fin juillet ?',
    steps: [
      { tool: 'staffing.search_consultants', args: '{practice:"audit_ssi", skills:["pentest"]}', result: '✓ 5 profils' },
      { tool: 'staffing.get_allocations', args: '{from:"20/07", days:10}', result: '✓ 2 disponibles' },
    ],
    summary:
      '2 consultants disponibles : Thomas Morel (Senior, OSCP, en intercontrat — disponible immédiatement) et Hugo Lambert (Junior, libéré du pentest Vidal le 25/07). Recommandation : T. Morel en lead, H. Lambert en binôme à partir du 27/07.',
    resultTitle: 'Proposition de staffing — 2 profils',
    resultSub: 'T. Morel (lead) + H. Lambert · à confirmer au staffing',
    resultAction: 'Ouvrir le staffing',
    target: 'staffing',
  },
  crm: {
    user: 'Crée une opportunité audit cloud 45 k€ pour Nexalog, échéance septembre',
    steps: [
      { tool: 'crm.get_client', args: '{name:"Nexalog"}', result: '✓ Client#4' },
      { tool: 'crm.create_opportunity', args: '{amount:45000, practice:"audit_ssi"}', result: '✓ Opportunity#221' },
    ],
    summary:
      'Opportunité « Audit cloud » créée pour Nexalog : 45 000 € HT, practice Audit SSI, étape Qualification, échéance 30/09/2026, propriétaire : vous. Elle apparaît dans le pipeline pondéré (+13,5 k€ à 30 %).',
    resultTitle: 'Opportunity#221 — Audit cloud · Nexalog',
    resultSub: '45 000 € HT · qualification · échéance 30/09/2026',
    resultAction: 'Ouvrir le CRM',
    target: 'crm',
  },
}

export function scenarioFor(key: string): Scenario | null {
  return scenarios[key] ?? null
}

/** Moteur mocké : reconnaît la demande par mots-clés et rejoue le scénario correspondant. */
export const mockEngine: AssistantEngine = {
  run(prompt: string): Scenario | null {
    const p = prompt.toLowerCase()
    if (p.includes('congé') || p.includes('conge')) return scenarios.leave
    if (p.includes('relance') || p.includes('facture')) return scenarios.invoices
    if (p.includes('dispo') || p.includes('pentest')) return scenarios.staffing
    if (p.includes('opportunité') || p.includes('opportunite') || p.includes('crée') || p.includes('cree')) return scenarios.crm
    return null
  },
}
