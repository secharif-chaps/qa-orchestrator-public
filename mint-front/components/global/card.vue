<template>
  <div class="bg-white dark:bg-slate-800 rounded-lg p-6 shadow-sm border border-slate-200 dark:border-slate-700 space-y-4">
    <div
      class="border-b border-slate-200 dark:border-slate-700 pb-3"
      v-if="title"
    >
      <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">
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
