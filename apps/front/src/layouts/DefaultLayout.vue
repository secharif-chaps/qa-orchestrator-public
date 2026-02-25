<template>
  <div class="bg-sage-950 h-full min-h-screen w-full min-w-screen">
    <Appbar />

    <div class="relative flex h-screen min-h-screen w-screen overflow-hidden">
      <div
        class="mt-[68px] max-h-[calc(100vh-68px)] overflow-y-auto rounded-tr-2xl transition-all duration-300"
        :class="[isFullscreen ? 'w-0' : 'w-full']"
      >
        <div class="dark:bg-sage-900 mx-auto w-full bg-white px-4 py-4 sm:px-6 lg:px-8">
          <Breadcrumbs />
        </div>
        <div class="dark:bg-sage-900 min-h-[calc(100vh-100px)] w-full bg-white">
          <div class="mx-auto max-w-7xl px-4 py-24 pt-4 sm:px-6 lg:px-8">
            <slot />
          </div>
        </div>
      </div>
      <div
        class="h-full shrink-0 grow transition-all duration-300"
        :class="[isFullscreen ? 'w-screen' : isOpen ? 'w-[320px]' : 'w-0']"
      >
        <div class="dark fixed h-screen pt-[70px]" :class="[isFullscreen ? 'w-full' : 'w-[320px]']">
          <Sidebar />
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import Appbar from '@/components/global/appbar.vue'
import Sidebar from '@/components/global/sidebar.vue'
import Breadcrumbs from '@/components/ui/Breadcrumbs.vue'
import { useSidebarStore } from '@/stores/sidebar'
import { computed } from 'vue'

const sidebarStore = useSidebarStore()

const isFullscreen = computed(() => sidebarStore.isFullscreen)

const isOpen = computed(() => sidebarStore.isOpen())
</script>
