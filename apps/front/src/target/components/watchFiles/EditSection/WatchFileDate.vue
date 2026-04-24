<template>
  <p class="text-sm text-slate-600">
    <span>{{ datePrefix }}</span>
    <span>{{ relativeDate }}</span>
  </p>
</template>

<script setup lang="ts">
import { toRef } from 'vue'
import { useRelativeTimeRef } from '@/composables/useDateTime'

interface Props {
  datePrefix: string
  date: string
}

const props = defineProps<Props>()

const relativeDate = useRelativeTimeRef(toRef(props, 'date'))
</script>
