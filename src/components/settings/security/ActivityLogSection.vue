<template>
  <div class="bg-base-100 border border-primary-stroke rounded-lg">
    <div class="px-6 py-4 border-b border-primary-stroke">
      <h2 class="text-lg font-semibold">{{ $t('settings.security.activity.title') }}</h2>
      <p class="text-sm text-secondary mt-1">
        {{ $t('settings.security.activity.description') }}
      </p>
    </div>
    <div class="px-6 py-6">
      <div class="space-y-3">
        <div
          v-for="activity in recentActivity"
          :key="activity.id"
          class="flex items-center space-x-3 p-3 border border-slate-200 dark:border-slate-700 rounded-lg"
        >
          <div class="flex-shrink-0">
            <i
              :class="[
                activity.type === 'security'
                  ? 'text-red-500 dark:text-red-400'
                  : 'text-green-500 dark:text-green-400',
                activity.icon,
              ]"
            ></i>
          </div>
          <div class="flex-1">
            <p class="text-sm font-medium">{{ activity.title }}</p>
            <p class="text-xs text-secondary">{{ activity.description }}</p>
            <p class="text-xs text-secondary">
              {{ formatDate(activity.timestamp) }}
            </p>
          </div>
          <div v-if="activity.location" class="text-xs text-secondary">
            {{ activity.location }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
interface Activity {
  id: string
  type: 'login' | 'security' | 'update'
  icon: string
  title: string
  description: string
  location?: string
  timestamp: Date
}

interface Props {
  recentActivity: Activity[]
}

const props = defineProps<Props>()

const formatDate = (date: Date) => {
  return date.toLocaleString()
}
</script>
