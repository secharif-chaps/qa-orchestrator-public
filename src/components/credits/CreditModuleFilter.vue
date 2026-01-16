<template>
  <Toggle
    v-model="selectedModule"
    :options="moduleOptions"
    variant="pill"
  />
</template>

<script setup lang="ts">
/**
 * Module filter toggle for credit statistics.
 * Allows selecting between all modules or a specific one.
 */
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Toggle } from '@owlint/feathers-vue'

interface Props {
  /** Include "All" option */
  includeAll?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  includeAll: true,
})

const { t } = useI18n()

const selectedModule = defineModel<string>({ default: 'all' })

const moduleOptions = computed(() => {
  const options = []

  if (props.includeAll) {
    options.push({
      value: 'all',
      label: t('credits.modules.all', 'Tous'),
      icon: 'fa fa-list',
    })
  }

  options.push(
    {
      value: 'screen',
      label: t('credits.modules.screen', 'Screen'),
      icon: 'fa fa-table-cells',
    },
    {
      value: 'target',
      label: t('credits.modules.target', 'Target'),
      icon: 'fa fa-eye',
    },
    {
      value: 'explore',
      label: t('credits.modules.explore', 'Explore'),
      icon: 'fa fa-diagram-project',
    }
  )

  return options
})
</script>
