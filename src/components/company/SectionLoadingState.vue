<template>
  <div class="flex flex-col items-center">
    <!-- Chapse Error Image -->
    <div class="mb-6">
      <img :src="chapseLoadingImage" alt="Loading" class="w-24 h-auto" />
    </div>

    <!-- Error Title -->
    <h3 class="text-xl font-semibold text-secondary mb-2 flex items-center gap-2">
      <i :class="icon" class="animate-spin"></i>
      <span>{{ title }}</span>
    </h3>

    <!-- Error Description -->
    <p class="text-secondary max-w-md mx-auto mb-4 text-center">
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
