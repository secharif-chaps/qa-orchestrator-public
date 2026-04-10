<template>
  <div
    class="border-sage-300 dark:bg-sage-800/50 dark:hover:bg-sage-800 rounded-sm border bg-white p-3 transition-colors"
  >
    <div class="flex items-start gap-3">
      <Badge variant="secondary" color="indigo" icon="fa-building" />

      <div class="min-w-0 flex-1">
        <div class="flex items-start justify-between gap-2">
          <h4 class="text-sage-900 truncate text-sm font-medium dark:text-white">{{ name }}</h4>
          <Tag intent="danger" label="-35" size="xs" />
        </div>
        <p class="text-sage-800 dark:text-sage-400 mt-1 text-xs">
          {{ $t('common.sidebar.tokens.createdBy') }} {{ ownerUsername }}
        </p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Badge, Tag } from '@owlint/feathers-vue'

defineProps<{
  name: string
  ownerUsername: string
}>()
</script>
