<template>
  <!-- Use v-show to prevent DOM removal during refetch which causes parentNode null errors -->
  <div v-show="modules.length > 0" class="flex items-center gap-2">
    <RouterLink
      v-for="module in modules"
      :key="module.name"
      :to="getModuleRoute(module)"
      class="transition-transform hover:scale-105"
      :class="{ 'pointer-events-none opacity-50': module.status === 'unavailable' }"
    >
      <Tag
        class="hidden lg:block"
        :label="$t(module.labelKey)"
        :icon="module.icon"
        :color="module.color"
        :variant="module.status !== 'enabled' ? 'secondary' : 'primary'"
      />
    </RouterLink>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useQuery } from '@pinia/colada'
import { Tag } from '@owlint/feathers-vue'
import { organizationModulesQuery } from '@/queries/tokens'
import { getModuleDisplayConfig, type ModuleDisplayConfig } from '@/config/modules'

const { organizationId } = defineProps<{ organizationId: string }>()

const { data: modulesData } = useQuery(() => organizationModulesQuery({ organizationId }))

const modules = computed(() => {
  if (!modulesData.value?.modules) return []

  return modulesData.value.modules.map((module) =>
    getModuleDisplayConfig(module.name, module.enabled),
  )
})

function getModuleRoute(module: ModuleDisplayConfig) {
  if (module.status !== 'enabled') return '/'

  if (module.name === 'screen') return '/companies/create'
  if (module.name === 'target') return '/target'

  return '/'
}
</script>
