<template>
  <div>
    <Tab v-if="tabs.length" :tabs="tabs" />
  </div>
</template>

<script setup lang="ts">
import { Tab, type NavigationTab } from '@owlint/feathers-vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useWatchFileStore } from '~/stores/watchFile'
import { RouteNames } from '~/types/route-names'

/* Temporary, until collect is fixed */
import { useAuth } from '~/composables/useAuth'
const { isInternalUser } = useAuth()
/* Temporary, until collect is fixed */

interface Props {
  watchFileId?: string
}

const { watchFileId = undefined } = defineProps<Props>()

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const watchFileStore = useWatchFileStore()

// Determine active tab: if we have a store ID (after silent navigation), we're on scope
// Otherwise, use the route name
const activeTab = computed(() => {
  if (watchFileStore.currentWatchFileId) {
    return [RouteNames.WATCH_FILES_SCOPE]
  }
  return route.matched.map((m) => m.name)
})

const isActive = (routeName: string) => activeTab.value.includes(routeName)

const tabs = computed<NavigationTab[]>(() => {
  return [
    {
      id: RouteNames.WATCH_FILES_RADAR,
      title: t('watch_files.tabs.radar'),
      click() {
        switchTab(RouteNames.WATCH_FILES_RADAR)
      },
      isActive: isActive(RouteNames.WATCH_FILES_RADAR),
      disabled: !watchFileId,
    },
    {
      id: RouteNames.WATCH_FILES_DOCUMENTS,
      title: t('watch_files.tabs.documents'),
      click() {
        switchTab(RouteNames.WATCH_FILES_DOCUMENTS)
      },
      isActive: isActive(RouteNames.WATCH_FILES_DOCUMENTS),
      disabled: !watchFileId || !isInternalUser.value, //Temporarily disabled for non internal users
    },
    {
      id: RouteNames.WATCH_FILES_SCOPE,
      title: t('watch_files.tabs.scope'),
      click() {
        switchTab(RouteNames.WATCH_FILES_SCOPE)
      },
      isActive: isActive(RouteNames.WATCH_FILES_SCOPE),
      disabled: !watchFileId,
    },
    {
      id: RouteNames.WATCH_FILES_AUDIT,
      title: t('watch_files.tabs.audit'),
      click() {
        switchTab(RouteNames.WATCH_FILES_AUDIT)
      },
      isActive: isActive(RouteNames.WATCH_FILES_AUDIT),
      disabled: !watchFileId,
    },
  ]
})

const switchTab = async (name: RouteNames) => {
  await router.push({
    name,
    params: { id: watchFileId },
    query: {
      ...route.query,
    },
  })
}
</script>
