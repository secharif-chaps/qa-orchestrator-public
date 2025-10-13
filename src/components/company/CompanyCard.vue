<template>
  <div class="flex flex-col gap-4">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <i v-if="icon" :class="[icon, 'text-secondary text-xl']"></i>
        <h1 class="text-2xl font-bold text-base">{{ title }}</h1>
      </div>

      <div class="flex items-center gap-2">
        <slot name="actions" />
      </div>
    </div>

    <!-- Content -->
    <slot />
  </div>
</template>

<script setup lang="ts">
interface Props {
  title?: string
  icon?: string
}

withDefaults(defineProps<Props>(), {
  title: '',
  icon: '',
})
</script>
