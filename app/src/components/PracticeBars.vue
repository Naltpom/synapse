<script setup lang="ts">
import { computed } from 'vue'

export interface PracticeRow {
  practice: string
  consultants: number
  staffed: number
}

const props = defineProps<{ rows: PracticeRow[] }>()

const max = computed(() => Math.max(...props.rows.map((r) => r.consultants), 1))
</script>

<template>
  <!-- Série unique (effectif) : les labels directs portent le détail staffés/total. -->
  <ul class="space-y-3">
    <li v-for="row in rows" :key="row.practice">
      <div class="mb-1 flex items-baseline justify-between text-[12.5px]">
        <span class="font-medium text-ink/80">{{ row.practice }}</span>
        <span class="tnum text-ink/50">{{ row.staffed }}/{{ row.consultants }} staffés</span>
      </div>
      <div class="h-2 overflow-hidden rounded-full bg-ink/6">
        <div
          class="h-full rounded-full bg-primary transition-[width] duration-500"
          :style="{ width: `${(row.consultants / max) * 100}%` }"
        />
      </div>
    </li>
  </ul>
</template>
