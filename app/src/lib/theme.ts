import { ref } from 'vue'

/**
 * Mode sombre par bascule de tokens CSS (voir main.css) :
 * les surfaces de coque (--color-shell) restent sombres dans les deux modes,
 * cloud/surface/ink s'inversent.
 */
const stored = localStorage.getItem('synapse-theme')

export const darkMode = ref(
  stored === 'dark' || (stored === null && window.matchMedia('(prefers-color-scheme: dark)').matches),
)

function apply(): void {
  document.documentElement.classList.toggle('dark', darkMode.value)
}

export function toggleTheme(): void {
  darkMode.value = !darkMode.value
  localStorage.setItem('synapse-theme', darkMode.value ? 'dark' : 'light')
  apply()
}

// Appliqué dès l'import (main.ts) pour éviter un flash de thème.
apply()
