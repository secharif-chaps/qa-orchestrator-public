<template>
  <div class="bg-bg3 min-h-screen h-full min-w-screen w-full">
    <Appbar />
    <div class="flex h-full">
      <Sidebar />

      <div class="py-24 pl-24 pr-4 w-full min-h-screen">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <slot />
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import Appbar from '@/components/global/appbar.vue'
import Sidebar from '@/components/global/sidebar.vue'
</script>
