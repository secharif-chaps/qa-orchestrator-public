<template>
  <div class="bg-sage-950 min-h-screen h-full min-w-screen w-full">
    <Appbar />

    <div class="flex min-h-screen h-screen w-screen relative">
      <div
        class="overflow-y-auto mt-[68px] max-h-[calc(100vh-68px)] rounded-tr-2xl transition-all duration-300"
        :class="[isFullscreen ? 'w-0' : 'w-full']"
      >
        <div class="bg-white dark:bg-sage-900 w-full mx-auto px-4 sm:px-6 lg:px-8 py-4">
          <Breadcrumbs />
        </div>
        <div
          class="bg-white min-h-[calc(100vh-125apx)] dark:bg-sage-900 w-full"
        >
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4 py-24">
          <slot />
          </div>
        </div>
      </div>
      <div
        class="h-full grow shrink-0 transition-all duration-300"
        :class="[isFullscreen ? 'w-screen' : isOpen ? 'w-[320px]' : 'w-0']"
      >
        <div class="pt-[70px] h-screen fixed" :class="[isFullscreen ? 'w-full' : ' w-[320px]']">
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
import Button from '@/components/ui/Button.vue'
import { useSidebarStore } from '@/stores/sidebar'
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const sidebarStore = useSidebarStore()

const isFullscreen = computed(() => sidebarStore.isFullscreen)

const isOpen = computed(() => sidebarStore.isOpen())

const route = useRoute()
</script>
