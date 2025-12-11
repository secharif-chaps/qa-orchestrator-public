<template>
  <div class="bg-sage-800/50 rounded-lg p-3 hover:bg-sage-800 transition-colors">
    <div class="flex items-start gap-3">
      <Badge variant="secondary" color="indigo" icon="fa fa-building" />

      <div class="flex-1 min-w-0">
        <div class="flex items-start justify-between gap-2">
          <h4 class="text-sm font-medium text-white truncate">{{ name }}</h4>
          <Tag intent="danger" label="-35" size="xs" />
        </div>
        <p class="text-xs text-sage-400 mt-1">
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
