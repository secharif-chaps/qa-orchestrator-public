<template>
  <NuxtLink
    :to="disabled ? '' : to"
    class="block h-full"
    :class="{ 'pointer-events-none opacity-50': disabled }"
  >
    <div
      class="bg-bg3 rounded-lg p-6 h-full hover:shadow-lg transition-shadow duration-300 flex flex-col gap-2"
    >
      <div class="flex justify-between items-center">
        <OIcon
          :icon="icon"
          type="secondary"
        />
        <i class="fa fa-arrow-right text-primary ml-1"></i>
      </div>
      <h3 class="text-primary">{{ title }}</h3>

      <p class="text-secondary text-sm flex-grow">{{ description }}</p>
    </div>
  </NuxtLink>
</template>

<script lang="ts" setup>
import { OIcon } from '@owlint/feathers-vue'

defineProps({
  title: {
    type: String,
    required: true,
  },
  description: {
    type: String,
    required: true,
  },
  icon: {
    type: String,
    required: true,
  },
  to: {
    type: String,
    required: true,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
})
</script>
