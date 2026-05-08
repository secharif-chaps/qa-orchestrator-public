<template>
  <Tooltip side="top" arrow class="cursor-default!">
    <template #default>
      <Tag icon="fa-lock" size="sm" intent="accent">
        {{ $t('target.watchFiles.edit.alert.enabled') }}
      </Tag>
    </template>
    <template #tooltip>
      <div class="tooltip-content">
        <p>{{ $t('target.watchFiles.edit.alert.enabled.tooltipIntro') }}</p>
        <ul>
          <li>
            {{ $t('target.watchFiles.edit.alert.enabled.tooltipItemSourceMonitoring') }}
          </li>
          <li>
            {{ $t('target.watchFiles.edit.alert.enabled.tooltipItemDocumentCollection') }}
          </li>
          <li>
            {{ $t('target.watchFiles.edit.alert.enabled.tooltipItemAiValidation') }}
          </li>
        </ul>
      </div>
    </template>
  </Tooltip>
</template>

<script lang="ts" setup>
import { Tag, Tooltip } from '@owlint/feathers-vue'
</script>

<style scoped>
.tooltip-content ul {
  list-style-type: disc;
  padding-left: 1.25rem;
  margin-top: 0.5rem;
}

.tooltip-content li {
  margin-bottom: 0.25rem;
}
</style>
