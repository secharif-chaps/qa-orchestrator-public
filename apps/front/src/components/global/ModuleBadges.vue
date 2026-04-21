<template>
  <div class="flex items-center gap-2">
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

    <!-- Discover badge: always visible; active appearance driven by flag, link by URL -->
    <a
      v-if="isDiscoverEnabled && discoverUrl"
      :href="discoverUrl"
      target="_blank"
      rel="noopener noreferrer"
      class="transition-transform hover:scale-105"
    >
      <Tag
        class="hidden lg:block"
        :label="$t('common.modules.discover')"
        icon="fa-regular fa-chart-pie-simple"
        color="cyan"
        variant="primary"
      />
    </a>
    <span v-else-if="isDiscoverEnabled">
      <Tag
        class="hidden lg:block"
        :label="$t('common.modules.discover')"
        icon="fa-regular fa-chart-pie-simple"
        color="cyan"
        variant="primary"
      />
    </span>
    <span v-else class="pointer-events-none opacity-50">
      <Tag
        class="hidden lg:block"
        :label="$t('common.modules.discover')"
        icon="fa-regular fa-chart-pie-simple"
        color="cyan"
        variant="secondary"
      />
    </span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useQuery } from '@pinia/colada'
import { Tag } from '@owlint/feathers-vue'
import { organizationModulesQuery } from '@/queries/tokens'
import { organizationFeatureFlagsQuery } from '@/queries/feature-flags'
import { getModuleDisplayConfig, type ModuleDisplayConfig } from '@/config/modules'

const { organizationId } = defineProps<{ organizationId: string }>()

const { data: modulesData } = useQuery(() => organizationModulesQuery({ organizationId }))
const { data: featureFlagsData } = useQuery(() => organizationFeatureFlagsQuery({ organizationId }))

const modules = computed(() => {
  if (!modulesData.value?.modules) return []

  return modulesData.value.modules
    .filter((module) => module.name !== 'stream') // stream is a feature flag, not a module badge
    .map((module) => getModuleDisplayConfig(module.name, module.enabled))
})

const discoverFlag = computed(() =>
  featureFlagsData.value?.feature_flags.find((f) => f.flag === 'discover'),
)

const isDiscoverEnabled = computed(() => discoverFlag.value?.enabled ?? false)
const discoverUrl = computed(() => (discoverFlag.value?.config?.url as string) || null)

function getModuleRoute(module: ModuleDisplayConfig) {
  if (module.status !== 'enabled') return '/'

  if (module.name === 'screen') return '/companies/create'
  if (module.name === 'target') return '/target'

  return '/'
}
</script>
