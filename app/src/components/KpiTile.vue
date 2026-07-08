<script setup lang="ts">
defineProps<{
  label: string
  value: string
  hint?: string
  alert?: boolean
}>()
</script>

<template>
  <div class="min-w-0 rounded-lg border border-ink/8 bg-white p-4">
    <p class="text-[12px] font-medium uppercase tracking-[0.08em] text-ink/45">{{ label }}</p>
    <p
      class="tnum mt-1.5 font-display text-[26px] font-semibold leading-none tracking-tight"
      :class="alert ? 'text-alert' : 'text-ink'"
    >
      {{ value }}
    </p>
    <p v-if="hint" class="mt-1.5 text-[12.5px] text-ink/50">{{ hint }}</p>
  </div>
</template>
