<template>
  <div class="bg-sage-800/50 hover:bg-sage-800 rounded-lg p-3 transition-colors">
    <div class="flex items-start gap-3">
      <Badge variant="secondary" color="indigo" icon="fa fa-building" />

      <div class="min-w-0 flex-1">
        <div class="flex items-start justify-between gap-2">
          <h4 class="truncate text-sm font-medium text-white">{{ name }}</h4>
          <Tag intent="danger" label="-35" size="xs" />
        </div>
        <p class="text-sage-400 mt-1 text-xs">
          {{ $t('sidebar.tokens.createdBy', 'Fiche créée par') }} {{ ownerUsername }}
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
