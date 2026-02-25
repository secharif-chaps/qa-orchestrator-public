<template>
  <div></div>
</template>

<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import { onMounted } from 'vue'

const { handleSilentCallback } = useAuthStore()

onMounted(async () => {
  try {
    await handleSilentCallback()
  } catch (error) {
    console.error('Silent callback error:', error)
  }
})
</script>
