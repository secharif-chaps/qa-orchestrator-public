<template>
  <RouterLink
    :to="disabled ? '' : to"
    class="block h-full"
    :class="{ 'pointer-events-none opacity-50': disabled }"
  >
    <div
      class="bg-base-100 border border-primary-stroke rounded-lg p-6 h-full hover:ring-4 hover:ring-primary/70 ring-offset-2 ring-offset-bg2  transition-shadow duration-300 flex flex-col gap-2"
    >
      <div class="flex justify-between items-center">
        <div class="bg-primary/20 size-10 rounded-lg flex items-center justify-center text-secondary"> 
          <i class="text-2xl fas " :class="loading ? ' fa-spinner-third fa-spin' : icon" />
        </div>
        <i class="fa fa-arrow-right text-secondary ml-1"></i>
      </div>
      <h3 class="text-secondary">{{ title }}</h3>

      <p class="text-secondary text-sm flex-grow">
        <span v-if="loading" class="flex items-center gap-2">
          <span>Loading...</span>
        </span>
        <p v-if="disabled" class="text-secondary">Coming soon</p>

        <span v-else>{{ description }}</span>
      </p>
    </div>
  </RouterLink>
</template>

<script lang="ts" setup>
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
  loading: {
    type: Boolean,
    default: false,
  },
})
</script>
