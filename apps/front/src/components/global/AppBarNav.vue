<template>
  <div class="flex items-center gap-1">
    <AppBarNavItem
      v-for="item in navItems"
      :key="item.state"
      :icon="item.icon"
      :active="sidebarStore.state === item.state"
      @click="sidebarStore.toggleState(item.state)"
    />
  </div>
</template>

<script lang="ts" setup>
import { useSidebarStore, type SidebarState } from '@/stores/sidebar'
import AppBarNavItem from './AppBarNavItem.vue'

const sidebarStore = useSidebarStore()

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
</script>
