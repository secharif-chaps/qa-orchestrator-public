<template>
  <div class="border-primary-stroke rounded-lg border-2 border-dashed p-4">
    <div class="py-8 text-center">
      <i :class="iconClass" class="text-secondary mb-3 text-3xl"></i>
      <h3 class="text-secondary mb-2 text-lg font-semibold">{{ title }}</h3>
      <p class="text-secondary mb-4">{{ description }}</p>
      <slot name="actions">
        <Button
          v-if="type === 'no-data'"
          variant="primary"
          icon="fa fa-plus"
          :label="$t('screen.company.list.create.title')"
          @click="$router.push('/search')"
        />
      </slot>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Button } from '@owlint/feathers-vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

interface Props {
  type: 'no-data' | 'no-results'
  searchQuery?: string
}

const props = defineProps<Props>()

defineEmits<{
  createCompany: []
}>()

const iconClass = computed(() => {
  return props.type === 'no-results' ? 'fa fa-search' : 'fa fa-building'
})

const title = computed(() => {
  return props.type === 'no-results'
    ? t('screen.company.empty.noResults.title')
    : t('screen.company.empty.noCompanies.title')
})

const description = computed(() => {
  if (props.type === 'no-results') {
    return props.searchQuery
      ? t('screen.company.empty.noResults.description', { query: props.searchQuery })
      : t('screen.company.empty.noResults.descriptionNoQuery')
  }
  return t('screen.company.empty.noCompanies.description')
})
</script>
