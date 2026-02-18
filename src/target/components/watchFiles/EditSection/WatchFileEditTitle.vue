<template>
  <div class="flex flex-col gap-1" data-watch-file-title-container>
    <!-- Read Mode -->
    <div
      v-if="!titleManager.isEditing.value"
      class="group flex items-center gap-2"
      :class="{
        'cursor-pointer': !disabled && canEdit,
        'cursor-not-allowed opacity-50': disabled,
      }"
      @dblclick.stop="titleManager.enterEditMode"
    >
      <template v-if="titleManager.displayTitle.value.length > titleManager.maxTitleDisplayLength">
        <OPopper placement="top" class="tooltip-wrapper">
          <template #tooltip>
            <div class="tooltip-content">
              {{ titleManager.displayTitle.value }}
            </div>
          </template>
          <h1
            class="max-w-120 truncate text-base"
            :class="{
              'text-gray-500': titleManager.isPlaceholder.value,
            }"
          >
            {{ titleManager.displayTitle.value }}
          </h1>
        </OPopper>
      </template>
      <h1
        v-else
        class="text-lg font-medium text-gray-900"
        :class="{
          'text-gray-500': titleManager.isPlaceholder.value,
        }"
      >
        {{ titleManager.displayTitle.value }}
      </h1>
      <Button
        v-if="canEdit"
        variant="tertiary"
        icon="fa-pen"
        size="sm"
        :aria-label="$t('watch_files.title.aria_label_edit_button')"
        :disabled="disabled"
        @click.stop="titleManager.enterEditMode"
      />
    </div>
    <!-- Edit Mode -->
    <div v-else class="flex flex-col">
      <div class="flex items-center gap-2">
        <Input
          id="watch-file-title-input"
          v-model="titleManager.editValue.value"
          class="max-w-112 min-w-96"
          :placeholder="$t('watch_files.title.placeholder')"
          :disabled="titleManager.isSaving.value"
          @keydown="titleManager.handleKeydown"
        />
        <Button
          icon="fa-check"
          size="sm"
          class="shrink-0"
          :aria-label="$t('watch_files.title.aria_label_confirm_button')"
          :loading="titleManager.isSaving.value"
          :disabled="!titleManager.isValidTitle.value || titleManager.isSaving.value"
          @click="titleManager.saveTitle"
        />
      </div>
      <div v-if="!titleManager.error.value" class="text-sm text-gray-600">
        {{ $t('watch_files.title.edit_helper') }}
      </div>
      <div v-if="titleManager.error.value" class="text-sm text-red-400">
        {{ titleManager.error.value }}
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Button, Input, OPopper } from '@owlint/feathers-vue'
import { computed, onMounted, onUnmounted, toRef } from 'vue'
import { useWatchFileTitle } from '~/composables/useWatchFileTitle'
import type { WatchFile } from '~/types/watchFile'

const props = withDefaults(
  defineProps<{
    watchFile?: WatchFile | null
    canEdit?: boolean
  }>(),
  {
    watchFile: null,
    title: '',
    canEdit: false,
  },
)

const disabled = computed(() => props.watchFile === null)

const titleManager = useWatchFileTitle({
  watchFile: toRef(props, 'watchFile'),
  canEdit: toRef(props, 'canEdit'),
})

onMounted(() => {
  document.addEventListener('click', titleManager.handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', titleManager.handleClickOutside)
})
</script>

<style scoped>
.tooltip-wrapper :deep(.tooltip) {
  max-width: 20rem; /* Same as input max-width (min-w-80 max-w-md) */
  white-space: normal !important;
  overflow-wrap: break-word;
  line-height: 1.4;
}

.tooltip-content {
  max-width: 20rem;
  white-space: normal;
  overflow-wrap: break-word;
  line-height: 1.4;
}
</style>
