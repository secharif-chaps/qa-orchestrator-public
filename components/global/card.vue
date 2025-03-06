<template>
  <div class="card space-y-4">
    <div
      class="border-b border-border-2 pb-2"
      v-if="title"
    >
      <p class="font-bold">
        {{ props.title }}
      </p>
    </div>
    <div>
      <slot>
        <CommingSoon />
      </slot>
    </div>
  </div>
</template>

<script setup lang="ts">
const props = defineProps<{
  title?: string
}>()
</script>
