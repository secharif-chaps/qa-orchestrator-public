<template>
  <Button
    icon="fa-filter"
    :variant="variant"
    :size="size"
    :aria-expanded="open"
    aria-haspopup="dialog"
    @click="open = true"
  >
    {{ buttonLabel }}
  </Button>
</template>

<script setup lang="ts">
import { Button } from '@owlint/feathers-vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

interface Props {
  count?: number
  label?: string
  variant?: 'secondary' | 'tertiary' | 'accent'
  size?: 'sm' | 'md'
}

const { count = 0, label = '', variant = 'accent' } = defineProps<Props>()

const open = defineModel<boolean>({ default: false })

const { t } = useI18n()

const buttonLabel = computed(() => {
  const base = label ?? t('common.filters.button.label')
  return count > 0 ? `${base} (${count})` : base
})
</script>
