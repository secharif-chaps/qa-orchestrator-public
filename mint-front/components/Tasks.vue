<template>
  <TaskList :company-id="companyId" />
</template>

<script setup lang="ts">
interface Props {
  companyId: number
}

defineProps<Props>()
</script> 