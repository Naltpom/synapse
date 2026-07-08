<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api } from '@/lib/api'
import { date } from '@/lib/format'
import StatusBadge from '@/components/StatusBadge.vue'

interface ProjectRow {
  id: number
  missionId: number | null
  name: string
  clientName: string
  manager: string
  progress: number
  health: string
  nextMilestone: string
  dueDate: string
  status: string
}

const projects = ref<ProjectRow[]>([])

onMounted(async () => {
  projects.value = await api.get<ProjectRow[]>('/api/projects')
})
</script>

<template>
  <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    <article v-for="project in projects" :key="project.id" class="rounded-lg border border-ink/8 bg-surface p-4">
      <div class="flex items-start justify-between gap-2">
        <div>
          <h2 class="text-[14.5px] font-semibold">{{ project.name }}</h2>
          <p class="text-[12.5px] text-ink/55">{{ project.clientName }} · {{ project.manager }}</p>
        </div>
        <StatusBadge :status="project.health" />
      </div>

      <div class="mt-4">
        <div class="mb-1 flex justify-between text-[12px] text-ink/55">
          <span>Avancement</span>
          <span class="tnum font-medium text-ink/75">{{ project.progress }} %</span>
        </div>
        <div class="h-1.5 overflow-hidden rounded-full bg-ink/6">
          <div
            class="h-full rounded-full"
            :class="project.health === 'rouge' ? 'bg-alert' : 'bg-primary'"
            :style="{ width: `${project.progress}%` }"
          />
        </div>
      </div>

      <dl class="mt-4 space-y-1 border-t border-ink/6 pt-3 text-[12.5px]">
        <div class="flex justify-between">
          <dt class="text-ink/50">Prochain jalon</dt>
          <dd class="font-medium text-ink/80">{{ project.nextMilestone }}</dd>
        </div>
        <div class="flex justify-between">
          <dt class="text-ink/50">Échéance</dt>
          <dd class="tnum text-ink/80">{{ date(project.dueDate) }}</dd>
        </div>
      </dl>
    </article>

    <p v-if="projects.length === 0" class="col-span-full py-16 text-center text-[13.5px] text-ink/45">
      Chargement des projets…
    </p>
  </div>
</template>
