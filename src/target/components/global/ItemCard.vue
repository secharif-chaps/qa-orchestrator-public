<template>
  <div
    class="flex flex-col justify-between gap-2 transition-shadow"
    :class="{
      'rounded-xs border border-gray-200 bg-white p-2 hover:shadow-sm':
        variant !== 'compact' && variant !== 'list' && variant !== 'minimal',
    }"
  >
    <div class="flex flex-1 grow flex-col gap-2">
      <div class="flex items-center justify-between gap-2">
        <div class="flex min-w-0 flex-1 items-center gap-2">
          <Logo
            :domain="domain ?? ''"
            :name="name"
            :alt="name"
            class="shrink-0 rounded-full object-contain"
            :width="24"
            :height="24"
          />
          <h4 class="truncate text-base font-medium text-gray-900">
            {{ name }}
          </h4>
          <Tag v-if="isNew" size="sm" intent="accent">
            {{ t('common.new') }}
          </Tag>
        </div>
        <div v-if="$slots.status" class="flex items-center gap-1">
          <slot name="status" />
        </div>
      </div>

      <div v-if="description && variant !== 'minimal'" class="text-sm text-gray-900">
        {{ description }}
      </div>
    </div>
    <div class="flex flex-col items-start gap-2">
      <slot name="footer">
        <!-- Default empty, use for UrlDomain or similar content -->
      </slot>

      <div v-if="variant === 'detail'">
        <slot name="action">
          <!-- Default empty, use for buttons, drawers, etc. -->
        </slot>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Tag } from '@owlint/feathers-vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Logo from '~/components/global/Logo.vue'

const { t } = useI18n()

interface Props {
  variant?: 'compact' | 'detail' | 'list' | 'minimal'
  domain?: string
  name: string
  description?: string
  date?: string | Date
}

const props = defineProps<Props>()

const isNew = computed(() => {
  if (!props.date) return false
  const dateValue = props.date instanceof Date ? props.date : new Date(props.date)
  return dateValue > new Date(Date.now() - 1000 * 60 * 60 * 24)
})
</script>
