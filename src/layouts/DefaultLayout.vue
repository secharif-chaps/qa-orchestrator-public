<template>
  <div class="bg-sage-950 min-h-screen h-full min-w-screen w-full">
    <Appbar />
    <div class="flex h-full">
      <div
        class="mt-[68px] rounded-tr-2xl max-h-[calc(100vh-68px)] w-full"
        :class="{ 'mr-0': !isOpen, 'mr-[320px]': isOpen }"
      >
        <div
          class="bg-white dark:bg-sage-900 fixed h-[calc(100vh-68px)] rounded-t-2xl left-0 top-[68px] transition-all z-10"
          :class="{ 'w-[calc(100%-320px)]': isOpen, 'w-full': !isOpen }"
        ></div>

        <div
          class="fixed h-10 rounded-t-2xl left-0 top-[68px] z-20 bg-gradient-to-b from-bg1 to-transparent dark:from-bg2"
          :class="{ 'w-[calc(100%-320px)]': isOpen, 'w-full': !isOpen }"
        ></div>

        <div class="z-10 relative py-4 pr-4 w-full">
          <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4">
            <!-- Breadcrumbs -->
            <div class="mb-6" v-if="route.name !== '/[...path]'">
              <Breadcrumbs />
            </div>

            <!-- Main content -->
            <slot />
          </div>
        </div>
      </div>

      <Sidebar />
    </div>
  </div>
</template>

<script lang="ts" setup>
import Appbar from '@/components/global/appbar.vue'
import Sidebar from '@/components/global/sidebar.vue'
import Breadcrumbs from '@/components/ui/Breadcrumbs.vue'
import { useSidebarStore } from '@/stores/sidebar'
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const sidebarStore = useSidebarStore()

const isOpen = computed(() => sidebarStore.isOpen())

const route = useRoute()
</script>
