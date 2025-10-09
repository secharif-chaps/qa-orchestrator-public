<template>
  <div class="flex items-start gap-3">
    <!-- Icon with badge -->
    <div class="relative flex-shrink-0">
      <Badge variant="secondary" color="primary" :icon="icon" size="md" />
      <div class="absolute -bottom-0.5 -right-0.5">
        <Badge variant="primary" color="success" icon="fa fa-plus" size="xs" />
      </div>
    </div>

    <div class="flex-1 min-w-0">
      <!-- Company/Folder Name -->
      <p class="text-sm font-semibold text-gray-900 dark:text-white">
        {{ target }}
      </p>
      <!-- Meta info: user and timestamp -->
      <div class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400 mt-0.5">
        <span class="flex items-center gap-1">
          <i class="fa fa-clock"></i>
          <span>{{ time }}</span>
        </span>
        <span>{{ $t('home.recentActivities.by', { username: '@' + username }) }}</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import Badge from '@/components/ui/Badge.vue'

interface Props {
  icon: string
  target: string
  username: string
  time: string
}

defineProps<Props>()
</script>
