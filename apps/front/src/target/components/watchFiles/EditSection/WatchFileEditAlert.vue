<template>
  <OPopper class="!cursor-default">
    <template #tooltip>
      <div
        v-sanitize-html="$t('target.watchFiles.edit.alert.enabled.tooltip')"
        class="tooltip-content"
      />
    </template>
    <Tag icon="fa-lock" size="sm" intent="accent">
      {{ $t('target.watchFiles.edit.alert.enabled') }}
    </Tag>
  </OPopper>
</template>

<script lang="ts" setup>
import { OPopper, Tag } from '@owlint/feathers-vue'
</script>

<style scoped>
.tooltip-content :deep(ul) {
  list-style-type: disc;
  padding-left: 1.25rem;
  margin-top: 0.5rem;
}

.tooltip-content :deep(li) {
  margin-bottom: 0.25rem;
}
</style>
