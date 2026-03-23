<template>
  <div
    class="border-sage-300 dark:border-sage-800 flex items-center justify-between border-b px-4 py-2"
  >
    <h2 class="font-bold text-black dark:text-white">{{ title }}</h2>
    <slot />
  </div>
</template>

<script lang="ts" setup>
interface Props {
  title: string
}

defineProps<Props>()
</script>
