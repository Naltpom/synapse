const euroFormatter = new Intl.NumberFormat('fr-FR', {
  style: 'currency',
  currency: 'EUR',
  maximumFractionDigits: 0,
})

const compactEuroFormatter = new Intl.NumberFormat('fr-FR', {
  style: 'currency',
  currency: 'EUR',
  notation: 'compact',
  maximumFractionDigits: 1,
})

const dateFormatter = new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium' })

export const euro = (value: number): string => euroFormatter.format(value)
export const euroCompact = (value: number): string => compactEuroFormatter.format(value)
export const date = (iso: string): string => dateFormatter.format(new Date(iso))

export function monthLabel(yearMonth: string): string {
  const [year, month] = yearMonth.split('-').map(Number)
  return new Intl.DateTimeFormat('fr-FR', { month: 'short' }).format(new Date(year, month - 1, 1))
}
