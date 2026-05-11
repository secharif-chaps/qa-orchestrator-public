<template>
  <div class="bg-sage-950 h-full min-h-screen w-full min-w-screen">
    <AppBar />
    <div class="relative flex h-full w-full">
      <div
        class="mt-16 h-[calc(100vh-4rem)] w-full overflow-y-auto rounded-t-2xl bg-white transition-all duration-300"
        :class="[isFullscreen ? 'w-0' : 'w-full']"
      >
        <slot name="default" />
      </div>
      <div
        class="h-full shrink-0 grow transition-all duration-300"
        :class="[isFullscreen ? 'w-screen' : isOpen ? 'w-[320px]' : 'w-0']"
      >
        <div class="fixed h-screen pt-[70px]" :class="[isFullscreen ? 'w-full' : 'w-[320px]']">
          <Sidebar />
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import Sidebar from '@target/components/global/Sidebar.vue'
import { config } from '@target/config'
import { useSidebarStore } from '@target/stores/sidebar'
import { useHead } from '@unhead/vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { locale } = useI18n()

useHead(() => ({
  titleTemplate: (title) => {
    return title ? `${title} - ${config.appName}` : config.appName
  },
  htmlAttrs: {
    lang: locale.value,
  },
}))

const sidebarStore = useSidebarStore()

const isFullscreen = computed(() => sidebarStore.isFullscreen)

const isOpen = computed(() => sidebarStore.isOpen())
</script>
