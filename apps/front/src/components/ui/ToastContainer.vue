<template>
  <div class="fixed top-4 right-4 z-50 space-y-2">
    <ONotification v-for="toast in toasts" :key="toast.id" :notification="toast" />
  </div>
</template>

<script setup lang="ts">
import { ONotification } from '@owlint/feathers-vue'
import { useToast } from '@/composables/useToast'

const { toasts } = useToast()
</script>
