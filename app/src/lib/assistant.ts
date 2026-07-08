import { ref } from 'vue'

/**
 * État global de l'assistant Synapse (palette ⌘K).
 * Le moteur (scénarios mockés puis vrai backend LLM+MCP) arrive avec la palette.
 */
export const assistantOpen = ref(false)

export function openAssistant(): void {
  assistantOpen.value = true
}

export function closeAssistant(): void {
  assistantOpen.value = false
}

export function toggleAssistant(): void {
  assistantOpen.value = !assistantOpen.value
}
