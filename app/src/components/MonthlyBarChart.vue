<script setup lang="ts">
import { computed, ref } from 'vue'
import { euro, monthLabel } from '@/lib/format'

export interface MonthPoint {
  month: string
  paid: number
  pending: number
}

const props = defineProps<{ points: MonthPoint[] }>()

const MAX_H = 150

const max = computed(() => Math.max(...props.points.map((p) => p.paid + p.pending), 1))

const bars = computed(() =>
  props.points.map((p, i) => {
    const total = p.paid + p.pending
    const height = Math.max(total > 0 ? 6 : 0, Math.round((total / max.value) * MAX_H))
    return {
      ...p,
      total,
      height,
      pendingHeight: total > 0 ? Math.round((p.pending / total) * height) : 0,
      current: i === props.points.length - 1,
    }
  }),
)

const hovered = ref<number | null>(null)
</script>

<template>
  <div class="relative">
    <div class="mt-5 flex h-[150px] items-end gap-[9px]" role="img" aria-label="Facturation émise sur les 12 derniers mois, payée et en attente">
      <div
        v-for="(bar, i) in bars"
        :key="bar.month"
        class="flex flex-1 cursor-default flex-col items-center justify-end gap-1.5 self-stretch"
        @mouseenter="hovered = i"
        @mouseleave="hovered = null"
      >
        <div class="flex w-full flex-col overflow-hidden rounded-[3px]" :style="{ height: `${bar.height}px` }">
          <div class="bg-[#c9d8fb]" :style="{ height: `${bar.pendingHeight}px` }" />
          <div class="flex-1 bg-primary" :style="hovered === i ? { background: '#0037c4' } : undefined" />
        </div>
        <span class="text-[11px]" :class="bar.current ? 'font-semibold text-primary' : 'text-ink/45'">
          {{ monthLabel(bar.month) }}
        </span>
      </div>
    </div>

    <!-- Infobulle -->
    <div
      v-if="hovered !== null"
      class="pointer-events-none absolute -top-2 rounded-md bg-shell px-2.5 py-1.5 text-[12px] text-white shadow-lg"
      :style="{ left: `${((hovered + 0.5) / bars.length) * 100}%`, transform: 'translateX(-50%)' }"
    >
      <span class="text-white/60">{{ monthLabel(bars[hovered].month) }} ·</span>
      <span class="tnum font-medium"> {{ euro(bars[hovered].paid) }} payée</span>
      <span class="tnum text-white/60"> + {{ euro(bars[hovered].pending) }} en attente</span>
    </div>

    <details class="mt-3">
      <summary class="cursor-pointer text-[12px] text-ink/45 hover:text-ink/70">Voir les données en tableau</summary>
      <table class="mt-2 w-full text-[12.5px]">
        <thead>
          <tr class="text-left text-ink/50">
            <th class="py-1 font-medium">Mois</th>
            <th class="py-1 text-right font-medium">Payée</th>
            <th class="py-1 text-right font-medium">En attente</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="point in points" :key="point.month" class="border-t border-ink/6">
            <td class="py-1">{{ point.month }}</td>
            <td class="tnum py-1 text-right">{{ euro(point.paid) }}</td>
            <td class="tnum py-1 text-right">{{ euro(point.pending) }}</td>
          </tr>
        </tbody>
      </table>
    </details>
  </div>
</template>
