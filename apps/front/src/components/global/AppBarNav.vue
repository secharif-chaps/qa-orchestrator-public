<template>
  <div class="flex items-center gap-1">
    <AppBarNavItem
      v-for="item in navItems"
      :key="item.state"
      :icon="item.icon"
      :active="sidebarStore.state === item.state"
      :unread-count="item.state === 'notifications' ? unreadCount : 0"
      @click="sidebarStore.toggleState(item.state)"
    />
  </div>
</template>

<script lang="ts" setup>
import { organizationActivitiesQuery } from '@/queries/organization'
import { useAuthStore } from '@/stores/auth'
import { useNotificationsStore } from '@/stores/notifications'
import { useSidebarStore, type SidebarState } from '@/stores/sidebar'
import { useQuery } from '@pinia/colada'
import { computed, onBeforeUnmount, onMounted } from 'vue'
import AppBarNavItem from './AppBarNavItem.vue'

const sidebarStore = useSidebarStore()
const authStore = useAuthStore()
const notificationsStore = useNotificationsStore()

interface NavItem {
  icon: string
  state: SidebarState
}

const navItems: NavItem[] = [
  { icon: 'fa-coins', state: 'tokens' },
  { icon: 'fa-message-bot', state: 'chapse' },
  { icon: 'fa-bell', state: 'notifications' },
  { icon: 'fa-bars', state: 'folders' },
]

const canViewActivities = computed(() => authStore.hasPermission('organization.read'))

const { data: activities, refetch } = useQuery(() => ({
  ...organizationActivitiesQuery(),
  enabled: canViewActivities.value,
}))

const unreadCount = computed(() => {
  if (!activities.value) return 0
  return activities.value.filter((a) => notificationsStore.isUnread(a.created_at)).length
})

// Pinia Colada has no built-in polling; drive it from a setInterval.
// Kept here (in a component mounted once, globally) so the badge refreshes
// even while the sidebar is on another tab.
const POLL_INTERVAL_MS = 30_000
let pollId: ReturnType<typeof setInterval> | null = null

onMounted(() => {
  pollId = setInterval(() => {
    if (canViewActivities.value) refetch()
  }, POLL_INTERVAL_MS)
})

onBeforeUnmount(() => {
  if (pollId !== null) clearInterval(pollId)
})
</script>
