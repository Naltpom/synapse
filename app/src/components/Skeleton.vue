<script setup lang="ts">
defineProps<{ lines?: number }>()
</script>

<template>
  <div class="animate-pulse space-y-3" aria-hidden="true">
    <div
      v-for="i in lines ?? 4"
      :key="i"
      class="h-4 rounded bg-ink/6"
      :style="{ width: `${100 - ((i - 1) % 3) * 18}%` }"
    />
  </div>
</template>
