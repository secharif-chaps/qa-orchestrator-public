<template>
  <div>
    <div class="flex items-center justify-between gap-2">
      <div class="flex items-center gap-1">
        <h3 class="font-chaps font-small leading-6 font-bold text-black">
          {{ title }}
        </h3>
        <button
          v-if="!readonly"
          class="flex cursor-pointer items-center justify-center focus:outline-none"
          :title="$t(refreshButtonTitle)"
          :disabled="loading"
          @click="$emit('refresh')"
        >
          <Icon
            :icon="refreshIcon"
            class="hover:text-primary-500 text-lg transition-colors"
            :spin="loading"
          />
        </button>
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
        :aria-label="addButtonText"
        :icon="addIcon"
        variant="tertiary"
        size="sm"
        :disabled="isNew"
        @click="$emit('add')"
      >
        {{ addButtonText }}
      </Button>
    </div>
    <span class="text-sm" :class="{ invisible: loading || error }">
      {{ subTitle }}
    </span>
  </div>
</template>

<script setup lang="ts">
import { Button, Icon, Tag } from '@owlint/feathers-vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

interface SectionListHeaderProps {
  title: string
  refreshButtonTitle?: string
  refreshIcon?: string
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

const {
  refreshIcon = 'fa-arrow-rotate-right',
  addButtonText = 'common.action.add',
  addIcon = 'fa-plus',
  subTitle = '',
  refreshButtonTitle = 'target.watchFiles.aria_label_refresh_button',
  readonly = false,
  lastUpdate = '',
  loading = false,
  error = '',
  batchSelection = false,
  isNew = false,
} = defineProps<SectionListHeaderProps>()

const { d } = useI18n()

const formattedLastUpdate = computed(() => {
  if (!lastUpdate) return ''
  return d(lastUpdate, 'long')
})

defineEmits<SectionListHeaderEmits>()
</script>
