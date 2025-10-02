<template>
  <div v-if="!isLoading && modules.length > 0" class="flex items-center gap-2">
    <div
      v-for="module in modules"
      :key="module.name"
      class="flex items-center gap-2 px-3 py-1.5 rounded-full transition-all border"
      :class="getBadgeClasses(module)"
    >
      <i :class="module.icon" class="text-sm" />
      <span class="text-sm font-medium">{{ module.label }}</span>

      <!-- Coming Soon label -->
      <span
        v-if="module.status === 'soon'"
        class="ml-1 px-2 py-0.5 bg-almond-300 dark:bg-almond-600 text-sage-900 dark:text-sage-100 text-xs rounded-full"
      >
        Bientôt disponible
      </span>

      <!-- Unavailable info icon -->
      <i
        v-if="module.status === 'unavailable'"
        class="fa fa-info-circle text-xs ml-1 opacity-60"
        title="Module non disponible"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useQuery } from '@pinia/colada'
import { workspaceModulesQuery } from '@/queries/tokens'
import { getModuleDisplayConfig } from '@/config/modules'
import type { ModuleName } from '@/types/tokens'

const { workspaceId } = defineProps<{ workspaceId: number }>()

// Fetch workspace modules
const { data: modulesData, isLoading } = useQuery(workspaceModulesQuery, () => ({ workspaceId }))

// Transform modules data for display
const modules = computed(() => {
  if (!modulesData.value?.modules) return []

  return modulesData.value.modules.map((module) => {
    // Explore module is hardcoded as coming soon
    // const comingSoon = module.name === 'explore'

    return getModuleDisplayConfig(module.name, module.enabled, false)
  })
})

// Get badge styling classes based on status and color
const getBadgeClasses = (module: ReturnType<typeof getModuleDisplayConfig>) => {
  const baseClasses = 'backdrop-blur-sm'

  // Disabled/unavailable state
  if (module.status === 'unavailable') {
    return `${baseClasses} bg-sage-400/20 dark:bg-sage-400/20 text-sage-200 border-sage-700/30`
  }

  // Color-coded classes for enabled and soon states
  const colorClasses = {
    purple: 'bg-purple-500/10 dark:bg-purple-500/15 text-purple-100 border-purple-500/30',
    green: 'bg-green-500/10 dark:bg-green-500/15 text-green-100 border-green-500/30',
    orange: 'bg-orange-500/10 dark:bg-orange-500/15 text-orange-100 border-orange-500/30',
    blue: 'bg-blue-500/10 dark:bg-blue-500/15 text-blue-100 border-blue-500/30',
  }

  return `${baseClasses} ${colorClasses[module.color]}`
}
</script>
