<template>
  <div class="bg-slate-100 rounded-lg">
    <div class="flex items-center justify-between p-3">
      <div class="flex items-center gap-3">
        <!-- Task Type Icon -->
        <div class="flex items-center gap-2">
          <div class="size-14 rounded-lg  flex items-center justify-center relative"
          :class="[
              {
                'bg-slate-100 text-slate-400': !status,
                'bg-orange-400 text-white': status === 'running',
                'bg-green-400 text-white': status === 'succeeded',
                'bg-red-400 text-white': status === 'error',
                'bg-slate-400 text-white': status === 'pending'
              }
            ]">
            <i 
            class="text-xl"
            :class="[
              getTaskIcon(type)
            ]"
            
          ></i>
          <i 
          v-if="status === 'running'"
            class="text-xl absolute animate-ping"
            :class="[
              getTaskIcon(type)
            ]"
          ></i>
          </div>
          
          
        </div>
        
        <div>
          <span class="capitalize font-medium">{{ type }}</span>
          <div v-if="status === 'error' && error" class="pb-3">
      <div class="">
        <div class="flex items-start gap-2">
          <div class="flex-1">
            <p class="text-red-700  font-bold text-xs mt-1">{{ error }}</p>
          </div>
        </div>
      </div>
    </div>
        </div>
        
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
    
    <!-- Error Message -->
    
  </div>
</template>

<script setup lang="ts">
import { OButton } from '@owlint/feathers-vue'
import type { TaskType, TaskStatus } from '~/types/task'

interface Props {
  type: TaskType
  status: TaskStatus | null
  error?: string | null
}

defineProps<Props>()
defineEmits<{
  (e: 'start'): void
  (e: 'restart'): void
}>()

// Icon mapping for different task types
const getTaskIcon = (taskType: TaskType): string => {
  const iconMap: Record<TaskType, string> = {
    profile: 'fas fa-user',
    digital: 'fas fa-globe',
    timeline: 'fas fa-history',
    products: 'fas fa-box',
    jobs: 'fas fa-briefcase',
    csr: 'fas fa-leaf',
    press: 'fas fa-newspaper',
    team: 'fas fa-users'
  }
  
  return iconMap[taskType] || 'fas fa-question'
}
</script> 