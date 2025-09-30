<template>
  <div
    class="w-[320px] flex flex-col justify-between fixed right-0 bg-sage-950 dark:bg-sidebar h-screen text-white pt-16 z-0 overflow-hidden"
  >
    <Transition
      mode="out-in"
      :enter-active-class="transitionClasses.enterActive"
      :leave-active-class="transitionClasses.leaveActive"
      :enter-from-class="transitionClasses.enterFrom"
      :enter-to-class="transitionClasses.enterTo"
      :leave-from-class="transitionClasses.leaveFrom"
      :leave-to-class="transitionClasses.leaveTo"
    >
      <component :is="currentComponent" :key="sidebarStore.state" />
    </Transition>

    <!-- Footer Actions -->
    <div class="border-t border-sage-800 px-4 py-3 flex items-center justify-around">
      <button
        class="flex flex-col items-center gap-1 text-sage-300 hover:text-white transition-colors"
        @click="$router.push('/settings/profile')"
      >
        <i class="fa fa-user text-lg"></i>
        <span class="text-xs">Profil</span>
      </button>
      <button
        class="flex flex-col items-center gap-1 text-sage-300 hover:text-white transition-colors"
        @click="$router.push('/settings')"
      >
        <i class="fa fa-cog text-lg"></i>
        <span class="text-xs">Paramètres</span>
      </button>
      <button
        class="flex flex-col items-center gap-1 text-sage-300 hover:text-white transition-colors"
        @click="$router.push('/accessibility')"
      >
        <i class="fa fa-universal-access text-lg"></i>
        <span class="text-xs">Accessibilité</span>
      </button>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { useSidebarStore } from '@/stores/sidebar'
import { computed } from 'vue'
import TokenSidebar from '@/components/sidebar/TokenSidebar.vue'
import ChapseSidebar from '@/components/sidebar/ChapseSidebar.vue'
import NotificationsSidebar from '@/components/sidebar/NotificationsSidebar.vue'
import FoldersSidebar from '@/components/sidebar/FoldersSidebar.vue'

const sidebarStore = useSidebarStore()

const currentComponent = computed(() => {
  switch (sidebarStore.state) {
    case 'tokens':
      return TokenSidebar
    case 'chapse':
      return ChapseSidebar
    case 'notifications':
      return NotificationsSidebar
    case 'folders':
      return FoldersSidebar
    default:
      return FoldersSidebar
  }
})

// Determine transition direction based on button order
const isTransitioningRight = computed(() => {
  return sidebarStore.isTransitioningRight(sidebarStore.previousState, sidebarStore.state)
})

// Dynamic transition classes based on direction
const transitionClasses = computed(() => {
  if (isTransitioningRight.value) {
    // Moving to the right (higher index) - slide from right to left
    return {
      enterActive: 'transition-all duration-150 ease-in',
      leaveActive: 'transition-all duration-150 ease-out',
      enterFrom: 'translate-x-32 opacity-0',
      enterTo: 'translate-x-0 opacity-100',
      leaveFrom: 'translate-x-0 opacity-100',
      leaveTo: '-translate-x-32 opacity-0',
    }
  } else {
    // Moving to the left (lower index) - slide from left to right
    return {
      enterActive: 'transition-all duration-150 ease-out',
      leaveActive: 'transition-all duration-150 ease-in',
      enterFrom: '-translate-x-32 opacity-0',
      enterTo: 'translate-x-0 opacity-100',
      leaveFrom: 'translate-x-0 opacity-100',
      leaveTo: 'translate-x-32 opacity-0',
    }
  }
})
</script>
