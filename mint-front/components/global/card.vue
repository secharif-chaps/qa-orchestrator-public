<template>
  <div class="bg-bg1 border border-border-2 rounded-lg p-6 space-y-4">
    <div
      class="border-b border-border-2 pb-3"
      v-if="title"
    >
      <h2 class="text-xl font-semibold">
        {{ props.title }}
      </h2>
    </div>
    <div>
      <slot>
        <CommingSoon text="Coming Soon" />
      </slot>
    </div>
  </div>
</template>

<script setup lang="ts">
const props = defineProps<{
  title?: string
}>()
</script>
