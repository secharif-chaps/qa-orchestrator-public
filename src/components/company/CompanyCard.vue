<template>
  <div class="grid grid-cols-12 gap-4 -mt-15 relative z-10">
    <!-- Title and buttons -->
    <div class="col-span-12 flex items-center justify-end">
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
import Export from './Export.vue'

// Props
defineProps<{
  title: string
  icon: string
}>()
</script>
