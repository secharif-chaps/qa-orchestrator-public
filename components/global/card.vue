<template>
  <div class="card space-y-4">
    <div
      class="border-b border-border-2 pb-2"
      v-if="title"
    >
      <h2 class="font-bold">
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
