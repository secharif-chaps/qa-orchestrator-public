<template>
  <p class="text-sm text-slate-600">
    <span>{{ datePrefix }}</span>
    <span>{{ formatTimeReactive(date) }}</span>
  </p>
</template>

<script setup lang="ts">
import { useTimeDisplay } from '~/composables/useTimeDisplay'

interface Props {
  datePrefix: string
  date: string
}

defineProps<Props>()

const { formatTimeReactive } = useTimeDisplay()
</script>
