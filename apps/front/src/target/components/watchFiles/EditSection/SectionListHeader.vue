<template>
  <div>
    <div class="flex items-center justify-between gap-2">
      <div class="flex items-center gap-1">
        <h3 class="font-chaps font-small leading-6 font-bold text-black">
          {{ title }}
        </h3>
        <Button
          v-if="!readonly"
          variant="tertiary"
          size="sm"
          :icon="refreshIcon"
          :title="$t(refreshButtonTitle)"
          :disabled="loading"
          :loading="loading"
          @click="$emit('refresh')"
        />
        <!-- Info Badge -->
      </div>
      <div v-if="lastUpdate" class="text-xs">
        <Tag intent="accent" icon="fa-clock" size="sm">
          {{
            $t('target.watchFiles.last_update.label', {
              date: formattedLastUpdate,
            })
          }}
        </Tag>
      </div>
      <Button
        v-if="(!readonly && !batchSelection) || isNew"
        :aria-label="resolvedAddButtonText"
        :icon="addIcon"
        variant="tertiary"
        size="sm"
        :disabled="isNew"
        @click="$emit('add')"
      >
        {{ resolvedAddButtonText }}
      </Button>
    </div>
    <span class="text-sm" :class="{ invisible: loading || error }">
      {{ subTitle }}
    </span>
  </div>
</template>

<script setup lang="ts">
import { Button, Tag } from '@owlint/feathers-vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

interface SectionListHeaderProps {
  title: string
  refreshButtonTitle?: string
  refreshIcon?: string
  /** Translated text for the add button. Falls back to t('common.action.add') when omitted. */
  addButtonText?: string
  addIcon?: string
  subTitle?: string
  readonly?: boolean
  lastUpdate?: string
  loading?: boolean
  error?: string
  batchSelection?: boolean
  isNew?: boolean
}

type SectionListHeaderEmits = (e: 'refresh' | 'add') => void

const { d, t } = useI18n()

const props = defineProps<SectionListHeaderProps>()

const {
  refreshIcon = 'fa-arrow-rotate-right',
  addIcon = 'fa-plus',
  subTitle = '',
  refreshButtonTitle = 'target.watchFiles.aria_label_refresh_button',
  readonly = false,
  lastUpdate = '',
  loading = false,
  error = '',
  batchSelection = false,
  isNew = false,
} = props

const resolvedAddButtonText = computed(() => props.addButtonText ?? t('common.action.add'))

const formattedLastUpdate = computed(() => {
  if (!lastUpdate) return ''
  return d(lastUpdate, 'long')
})

defineEmits<SectionListHeaderEmits>()
</script>
