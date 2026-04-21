<template>
  <Tab :tabs="tabs" variant="inner" />
</template>

<script setup lang="ts">
import { Tab, type NavigationTab } from '@owlint/feathers-vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'

interface Props {
  folderId: string
  showStreamsTab: boolean
}

const { folderId, showStreamsTab } = defineProps<Props>()

const route = useRoute()
const router = useRouter()
const { t } = useI18n()

const isItemsActive = computed(() => {
  return route.path === `/folders/${folderId}`
})

const isStreamsActive = computed(() => {
  return route.path.startsWith(`/folders/${folderId}/streams`)
})

const tabs = computed<NavigationTab[]>(() => {
  const items: NavigationTab[] = [
    {
      id: 'items',
      icon: 'fas fa-building',
      title: t('common.folder.tabs.items'),
      isActive: isItemsActive.value,
      click: () => router.push(`/folders/${folderId}`),
    },
  ]

  if (showStreamsTab) {
    items.push({
      id: 'streams',
      icon: 'fas fa-paper-plane',
      title: t('common.folder.tabs.broadcasts'),
      isActive: isStreamsActive.value,
      click: () => router.push(`/folders/${folderId}/streams`),
    })
  }

  return items
})
</script>
