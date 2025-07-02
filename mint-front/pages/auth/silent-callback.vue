<template>
  <div></div>
</template>

<script setup lang="ts">
definePageMeta({
  layout: false,
  auth: false
})

const { handleSilentCallback } = useAuth()

onMounted(async () => {
  try {
    await handleSilentCallback()
  } catch (error) {
    console.error('Silent callback error:', error)
  }
})
</script>