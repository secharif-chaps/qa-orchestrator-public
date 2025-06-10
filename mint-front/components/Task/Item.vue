<template>
  <div class="flex items-center justify-between p-3 bg-slate-100 rounded-lg">
    <div class="flex items-center gap-3">
      <div class="relative">
        <div
          class="size-3 rounded-full"
          :class="{
            'border-2 border-gray-300 border-dashed': !status,
            'bg-orange-500': status === 'running',
            'bg-green-500': status === 'succeeded',
            'bg-red-500': status === 'error',
            'bg-slate-500': status === 'pending'
          }"
        ></div>
        <div
          v-if="status === 'running'"
          class="absolute inset-0 size-3 rounded-full bg-orange-500 animate-ping"
        ></div>
      </div>
      <span class="capitalize font-medium">{{ type }}</span>
    </div>
    
    <OButton
      type="tertiary"
      v-if="!status || status === 'pending'"
      @click="$emit('start')"
    >
      <i class="fa fa-play"></i>
    </OButton>
    
    <OButton
      type="tertiary"
      v-else-if="status === 'succeeded' || status === 'error'"
      @click="$emit('restart')"
    >
      <i class="fa fa-refresh"></i>
    </OButton>
  </div>
</template>

<script setup lang="ts">
import { OButton } from '@owlint/feathers-vue'
import type { TaskType, TaskStatus } from '~/types/task'

interface Props {
  type: TaskType
  status: TaskStatus | null
}

defineProps<Props>()
defineEmits<{
  (e: 'start'): void
  (e: 'restart'): void
}>()
</script> 