import { ref } from 'vue'

/**
 * Préférences d'apparence (thème + couleur d'accent), persistées en localStorage
 * et appliquées en direct par bascule de tokens CSS.
 *
 * L'accent surcharge --color-primary et ses dérivés en style inline sur <html> :
 * l'inline l'emporte sur les règles `.dark {}` de main.css, donc les teintes
 * soft/hover sont choisies ici selon le mode clair/sombre.
 */
export interface Accent {
  key: string
  label: string
  primary: string
  strongLight: string
  strongDark: string
  softLight: string
  softDark: string
}

export const accents: Accent[] = [
  { key: 'synetis', label: 'Synetis', primary: '#0048fe', strongLight: '#0037c4', strongDark: '#6b9aff', softLight: '#e6edff', softDark: '#142650' },
  { key: 'indigo', label: 'Indigo', primary: '#4f46e5', strongLight: '#4338ca', strongDark: '#a5b4fc', softLight: '#eef2ff', softDark: '#1e1b4b' },
  { key: 'violet', label: 'Violet', primary: '#7c3aed', strongLight: '#6d28d9', strongDark: '#c4b5fd', softLight: '#f5f3ff', softDark: '#2e1065' },
  { key: 'ocean', label: 'Océan', primary: '#0891b2', strongLight: '#0e7490', strongDark: '#67e8f9', softLight: '#ecfeff', softDark: '#083344' },
  { key: 'emerald', label: 'Émeraude', primary: '#059669', strongLight: '#047857', strongDark: '#6ee7b7', softLight: '#ecfdf5', softDark: '#064e3b' },
]

const storedTheme = localStorage.getItem('synapse-theme')
const storedAccent = localStorage.getItem('synapse-accent')

// Défaut : thème clair, sauf choix explicite « dark ».
export const darkMode = ref(storedTheme === 'dark')
export const accentKey = ref(accents.some((a) => a.key === storedAccent) ? (storedAccent as string) : 'synetis')

export const preferencesOpen = ref(false)

function currentAccent(): Accent {
  return accents.find((a) => a.key === accentKey.value) ?? accents[0]
}

function apply(): void {
  const root = document.documentElement
  root.classList.toggle('dark', darkMode.value)
  const a = currentAccent()
  root.style.setProperty('--color-primary', a.primary)
  root.style.setProperty('--color-primary-strong', darkMode.value ? a.strongDark : a.strongLight)
  root.style.setProperty('--color-primary-soft', darkMode.value ? a.softDark : a.softLight)
}

export function setDark(value: boolean): void {
  darkMode.value = value
  localStorage.setItem('synapse-theme', value ? 'dark' : 'light')
  apply()
}

export function toggleTheme(): void {
  setDark(!darkMode.value)
}

export function setAccent(key: string): void {
  if (!accents.some((a) => a.key === key)) return
  accentKey.value = key
  localStorage.setItem('synapse-accent', key)
  apply()
}

export function openPreferences(): void {
  preferencesOpen.value = true
}

export function closePreferences(): void {
  preferencesOpen.value = false
}

// Appliqué dès l'import (main.ts) pour éviter un flash de thème.
apply()
