<template>
  <div class="flex flex-wrap content-center items-center gap-4">
    <ModuleCard
      v-for="item in visibleModules"
      :key="item.module.key"
      :module="item.module"
      @action="item.onAction?.()"
    />
  </div>
</template>

<script setup lang="ts">
import type { FeatureFlagConfig } from '@/types/feature-flags'
import type { ModuleConfig } from '@/types/tokens'
import type { ModuleCardConfig, ModuleCardType } from './ModuleCard.vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import ModuleCard from './ModuleCard.vue'

interface ModuleEntry {
  module: ModuleCardConfig
  onAction?: () => void
}

interface Props {
  featureFlags?: FeatureFlagConfig[]
  modulesData?: ModuleConfig[]
}

const { featureFlags = [], modulesData = [] } = defineProps<Props>()

const { t } = useI18n()
const router = useRouter()

// Discover feature flag logic (preserved from TAR-1422)
const discoverFlag = computed(() => featureFlags.find((f) => f.flag === 'discover'))
const isDiscoverEnabled = computed(() => discoverFlag.value?.enabled ?? false)
const discoverUrl = computed(() => (discoverFlag.value?.config?.url as string) || null)

// Compute module card type from backend data
const getModuleType = (key: string): ModuleCardType => {
  const moduleData = modulesData.find((m) => m.name === key)
  if (!moduleData) return 'soon'
  return moduleData.enabled ? 'default' : 'disabled'
}

const visibleModules = computed<ModuleEntry[]>(() => {
  const screenType = getModuleType('screen')
  const targetType = getModuleType('target')
  const exploreType = getModuleType('explore')
  const discoverType: ModuleCardType = isDiscoverEnabled.value ? 'default' : 'soon'

  return [
    {
      module: {
        key: 'screen',
        theme: 'indigo',
        type: screenType,
        icon: 'fa-regular fa-buildings',
        title: t('dashboard.home.modules.screen.cardTitle'),
        description: t('dashboard.home.modules.screen.cardDescription'),
        statLine: t('dashboard.home.modules.screen.cardStat', { count: 12 }),
        actionLabel: t('dashboard.home.modules.screen.cardAction'),
        comingSoonLabel: t('dashboard.home.modules.status.comingSoon'),
      },
      onAction: () => router.push('/companies/create'),
    },
    {
      module: {
        key: 'target',
        theme: 'cherry',
        type: targetType,
        icon: 'fa-regular fa-file-lines',
        title: t('dashboard.home.modules.target.cardTitle'),
        description: t('dashboard.home.modules.target.cardDescription'),
        statLine: t('dashboard.home.modules.target.cardStat', { count: 8 }),
        actionLabel: t('dashboard.home.modules.target.cardAction'),
        comingSoonLabel: t('dashboard.home.modules.status.comingSoon'),
      },
    },
    {
      module: {
        key: 'explore',
        theme: 'yellow',
        type: exploreType,
        icon: 'fa-regular fa-chart-network',
        title: t('dashboard.home.modules.explore.cardTitle'),
        description: t('dashboard.home.modules.explore.cardDescription'),
        statLine: t('dashboard.home.modules.explore.cardStat', { count: 24 }),
        actionLabel: t('dashboard.home.modules.explore.cardAction'),
        comingSoonLabel: t('dashboard.home.modules.status.comingSoon'),
      },
    },
    {
      module: {
        key: 'discover',
        theme: 'cyan',
        type: discoverType,
        icon: 'fa-regular fa-chart-pie-simple',
        title: t('dashboard.home.modules.discover.cardTitle'),
        description: t('dashboard.home.modules.discover.cardDescription'),
        statLine: t('dashboard.home.modules.discover.cardStat', { count: 8 }),
        actionLabel: t('dashboard.home.modules.discover.cardAction'),
        comingSoonLabel: t('dashboard.home.modules.status.comingSoon'),
      },
      onAction: () => {
        if (discoverUrl.value) {
          window.open(discoverUrl.value, '_blank', 'noopener,noreferrer')
        }
      },
    },
  ]
})
</script>
