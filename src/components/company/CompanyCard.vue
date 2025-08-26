<template>
  <div class="grid grid-cols-12 gap-4">
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
import { ref } from 'vue'
import Export from './Export.vue'

// Props
defineProps<{
  title: string
  icon: string
}>()
</script>
