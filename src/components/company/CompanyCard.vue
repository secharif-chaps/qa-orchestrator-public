<template>
  <div class="max-w-7xl mx-auto grid grid-cols-12 gap-4 px-4 sm:px-6 lg:px-8">
    <!-- Back button -->
    <div class="col-span-12">
      <Button variant="tertiary" icon="fa fa-arrow-left" label="Back" @click="$router.back()" />
    </div>

    <!-- Title and buttons -->
    <div class="col-span-12 flex items-center justify-between">
      <div class="flex items-center space-x-4">
        <h1 class="text-3xl font-semibold">{{ title }}</h1>
      </div>
      <div class="flex gap-2">
        <slot name="actions"></slot>
        <Export />
      </div>
    </div>

    <!-- Loading state -->
    <div class="col-span-12">
      <slot name="loading"></slot>
    </div>

    <!-- Main content container -->
    <div class="col-span-12">
      <slot></slot>
    </div>
  </div>
</template>

<script lang="ts" setup>
import Button from '@/components/ui/Button.vue'
import { ref } from 'vue'
import Export from './Export.vue'
import Chat from './Chat.vue'

// Props
defineProps<{
  title: string
  icon: string
}>()

// Chat visibility state
const showAiChat = ref(false)
</script>

<style>
.container {
  container-type: inline-size;
  container-name: content;
}

@container content (max-width: 1200px) {
  .card {
    grid-template-columns: repeat(2, 1fr);
  }
}

@container content (max-width: 768px) {
  .card {
    grid-template-columns: 1fr;
  }
}
</style>
