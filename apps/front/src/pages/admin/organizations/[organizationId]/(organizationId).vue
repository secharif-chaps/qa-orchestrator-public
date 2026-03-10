<template>
  <div></div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute('/admin/organizations/[organizationId]/(organizationId)')
const router = useRouter()

// Redirect to profile subpage
onMounted(() => {
  const organizationId = route.params.organizationId
  router.replace(`/admin/organizations/${organizationId}/profile`)
})
</script>
