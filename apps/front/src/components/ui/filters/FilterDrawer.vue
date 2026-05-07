<template>
  <Drawer v-model="open" :title="title" :position="position" icon="fa-filter" :to="to">
    <div class="absolute inset-0 flex flex-col overflow-hidden">
      <div class="scrollable min-h-0 flex-1 p-6 pr-3">
        <slot v-if="isLoading" name="loading">
          <div class="text-neutral-black-font p-4 text-sm">
            {{ t('common.filters.loading') }}
          </div>
        </slot>
        <slot v-else-if="error" name="error">
          <div class="text-error-content p-4 text-sm" role="alert">
            {{ errorTitle ?? t('common.filters.errorTitle') }}
          </div>
        </slot>
        <slot v-else />
      </div>
      <div class="flex w-full shrink-0 items-center gap-1.5 bg-white p-6">
        <Button @click="onConfirm">
          {{ confirmLabel }}
        </Button>
        <Button v-if="filtersCount" variant="tertiary" icon="fa-rotate-left" @click="onReset">
          {{ resetLabel }}
        </Button>
      </div>
    </div>
  </Drawer>
</template>

<script setup lang="ts">
import Drawer from '@/components/ui/Drawer.vue'
import { Button } from '@owlint/feathers-vue'
import { type RendererElement } from 'vue'
import { useI18n } from 'vue-i18n'

interface Props {
  isLoading?: boolean
  error?: Error | null
  filtersCount?: number
  title: string
  confirmLabel: string
  resetLabel: string
  errorTitle?: string
  to?: string | RendererElement
  position?: 'left' | 'right'
}

interface Emits {
  confirm: []
  reset: []
}

const {
  isLoading = false,
  error = null,
  filtersCount = 0,
  errorTitle = undefined,
  to = 'body',
  position = 'left',
} = defineProps<Props>()

const open = defineModel<boolean>({ default: false })
const emit = defineEmits<Emits>()

const { t } = useI18n()

const onConfirm = () => {
  emit('confirm')
  open.value = false
}

const onReset = () => {
  emit('reset')
  open.value = false
}
</script>
