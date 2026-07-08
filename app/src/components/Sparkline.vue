<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ points: number[] }>()

const W = 96
const H = 34
const PAD = 3

const polyline = computed(() => {
  const values = props.points
  if (values.length < 2) return ''
  const min = Math.min(...values)
  const max = Math.max(...values)
  const range = max - min || 1
  return values
    .map((v, i) => {
      const x = PAD + (i / (values.length - 1)) * (W - 2 * PAD)
      const y = PAD + (1 - (v - min) / range) * (H - 2 * PAD)
      return `${Math.round(x * 10) / 10},${Math.round(y * 10) / 10}`
    })
    .join(' ')
})
</script>

<template>
  <svg :width="W" :height="H" :viewBox="`0 0 ${W} ${H}`" aria-hidden="true">
    <polyline :points="polyline" fill="none" stroke="#6b9aff" stroke-width="2" />
  </svg>
</template>
