<script setup lang="ts">
defineProps<{ size?: number; animated?: boolean }>()
</script>

<template>
  <!-- Trois nœuds reliés : la synapse qui connecte les practices du cabinet. -->
  <svg
    :width="size ?? 28"
    :height="size ?? 28"
    viewBox="0 0 32 32"
    fill="none"
    aria-hidden="true"
  >
    <path d="M8 24 L16 8 M16 8 L26 20 M8 24 L26 20" stroke="currentColor" stroke-width="1.6" opacity="0.45" />
    <circle cx="8" cy="24" r="3.4" fill="currentColor" :class="{ pulse: animated }" style="--d: 0s" />
    <circle cx="16" cy="8" r="4.2" fill="#0048fe" :class="{ pulse: animated }" style="--d: 0.4s" />
    <circle cx="26" cy="20" r="2.8" fill="currentColor" :class="{ pulse: animated }" style="--d: 0.8s" />
  </svg>
</template>

<style scoped>
.pulse {
  animation: pulse 3.2s ease-in-out infinite;
  animation-delay: var(--d);
  transform-origin: center;
  transform-box: fill-box;
}
@keyframes pulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.25); }
}
</style>
