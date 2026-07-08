<script setup lang="ts">
import { computed, ref } from 'vue'
import { euro, euroCompact, monthLabel } from '@/lib/format'

export interface MonthPoint {
  month: string
  amount: number
}

const props = defineProps<{ points: MonthPoint[] }>()

const W = 640
const H = 200
const PAD = { top: 14, right: 8, bottom: 24, left: 58 }

const max = computed(() => Math.max(...props.points.map((p) => p.amount), 1))
const maxIndex = computed(() => props.points.findIndex((p) => p.amount === max.value))

const innerW = W - PAD.left - PAD.right
const innerH = H - PAD.top - PAD.bottom
const slot = computed(() => innerW / props.points.length)
const barW = computed(() => Math.min(18, slot.value - 10))

const y = (amount: number) => PAD.top + innerH * (1 - amount / max.value)
const x = (i: number) => PAD.left + i * slot.value + (slot.value - barW.value) / 2

/** Barre fine, sommet arrondi 4 px, ancrée à la ligne de base. */
function barPath(i: number, amount: number): string {
  const x0 = x(i)
  const y0 = y(amount)
  const base = PAD.top + innerH
  const w = barW.value
  const r = Math.min(4, w / 2, base - y0)
  return `M${x0},${base} L${x0},${y0 + r} Q${x0},${y0} ${x0 + r},${y0} L${x0 + w - r},${y0} Q${x0 + w},${y0} ${x0 + w},${y0 + r} L${x0 + w},${base} Z`
}

const gridLines = computed(() =>
  [0.25, 0.5, 0.75, 1].map((t) => ({ y: PAD.top + innerH * (1 - t), value: max.value * t })),
)

const hovered = ref<number | null>(null)
</script>

<template>
  <div class="relative">
    <svg :viewBox="`0 0 ${W} ${H}`" class="w-full" role="img" aria-label="Facturation mensuelle des 12 derniers mois">
      <!-- Grille discrète -->
      <g v-for="line in gridLines" :key="line.y">
        <line :x1="PAD.left" :x2="W - PAD.right" :y1="line.y" :y2="line.y" stroke="#181d27" stroke-opacity="0.07" />
        <text :x="PAD.left - 6" :y="line.y + 3.5" text-anchor="end" class="fill-ink/40 text-[9.5px]">
          {{ euroCompact(line.value) }}
        </text>
      </g>
      <line :x1="PAD.left" :x2="W - PAD.right" :y1="PAD.top + innerH" :y2="PAD.top + innerH" stroke="#181d27" stroke-opacity="0.18" />

      <!-- Barres -->
      <g v-for="(point, i) in points" :key="point.month">
        <path
          :d="barPath(i, point.amount)"
          :fill="hovered === i ? '#0037c4' : '#0048fe'"
          @mouseenter="hovered = i"
          @mouseleave="hovered = null"
        />
        <!-- Cible de survol plus large que la barre -->
        <rect
          :x="PAD.left + i * slot"
          :y="PAD.top"
          :width="slot"
          :height="innerH"
          fill="transparent"
          @mouseenter="hovered = i"
          @mouseleave="hovered = null"
        />
        <!-- Label direct sur le maximum uniquement -->
        <text
          v-if="i === maxIndex"
          :x="x(i) + barW / 2"
          :y="y(point.amount) - 5"
          text-anchor="middle"
          class="tnum fill-ink/70 text-[9.5px] font-medium"
        >
          {{ euroCompact(point.amount) }}
        </text>
        <text
          :x="PAD.left + i * slot + slot / 2"
          :y="H - 8"
          text-anchor="middle"
          class="fill-ink/45 text-[9.5px]"
        >
          {{ monthLabel(point.month) }}
        </text>
      </g>
    </svg>

    <!-- Infobulle -->
    <div
      v-if="hovered !== null"
      class="pointer-events-none absolute -top-1 rounded-md bg-ink px-2.5 py-1.5 text-[12px] text-white shadow-lg"
      :style="{ left: `${((x(hovered) + barW / 2) / W) * 100}%`, transform: 'translateX(-50%)' }"
    >
      <span class="text-white/60">{{ monthLabel(points[hovered].month) }} ·</span>
      <span class="tnum font-medium"> {{ euro(points[hovered].amount) }}</span>
    </div>

    <details class="mt-2">
      <summary class="cursor-pointer text-[12px] text-ink/45 hover:text-ink/70">Voir les données en tableau</summary>
      <table class="mt-2 w-full text-[12.5px]">
        <thead>
          <tr class="text-left text-ink/50">
            <th class="py-1 font-medium">Mois</th>
            <th class="py-1 text-right font-medium">Montant HT</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="point in points" :key="point.month" class="border-t border-ink/6">
            <td class="py-1">{{ point.month }}</td>
            <td class="tnum py-1 text-right">{{ euro(point.amount) }}</td>
          </tr>
        </tbody>
      </table>
    </details>
  </div>
</template>
