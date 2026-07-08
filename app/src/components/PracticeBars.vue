<script setup lang="ts">
export interface PracticeRow {
  practice: string
  consultants: number
  staffed: number
  occupancyRate: number
}

defineProps<{ rows: PracticeRow[] }>()
</script>

<template>
  <div class="flex flex-col gap-3">
    <div v-for="row in rows" :key="row.practice">
      <div class="mb-[5px] flex justify-between text-[12.5px]">
        <span>{{ row.practice }}</span>
        <span class="tnum text-ink/55">{{ row.consultants }} · {{ row.occupancyRate }} %</span>
      </div>
      <div class="h-1.5 rounded-[3px] bg-ink/6">
        <div
          class="h-full rounded-[3px] transition-[width] duration-500"
          :class="row.occupancyRate < 80 ? 'bg-[#7ba0f6]' : 'bg-primary'"
          :style="{ width: `${row.occupancyRate}%` }"
        />
      </div>
    </div>
  </div>
</template>
