<template>
  <div class="flex flex-col items-center">
    <!-- Chapse Error Image -->
    <div class="mb-6">
      <img :src="chapseLoadingImage" alt="Loading" class="h-auto w-24" />
    </div>

    <!-- Error Title -->
    <h3 class="text-neutral-black-font mb-2 flex items-center gap-2 text-xl font-semibold">
      <i :class="icon" class="animate-spin"></i>
      <span>{{ title }}</span>
    </h3>

    <!-- Error Description -->
    <p class="text-neutral-black-font mx-auto mb-4 max-w-112 text-center">
      {{ description }}
    </p>
  </div>
</template>

<script lang="ts" setup>
import chapseLoadingImage from '@/assets/chapse/loading.svg'

const title = 'Loading'
const description = 'La tâche est en cours de traitement... Veuillez patienter.'
const icon = 'fa fa-spinner-third'
</script>
