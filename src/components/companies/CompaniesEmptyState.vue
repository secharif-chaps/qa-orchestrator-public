<template>
  <div class="border-2 border-dashed border-primary-stroke rounded-lg p-4">
    <div class="text-center py-8">
      <i :class="iconClass" class="text-3xl text-primary-light-content mb-3"></i>
      <h3 class="text-lg font-semibold text-primary-light-content mb-2">{{ title }}</h3>
      <p class="text-primary-light-content mb-4">{{ description }}</p>
      <slot name="actions">
        <Button
          v-if="type === 'no-data'"
          variant="primary"
          icon="fa fa-plus"
          :label="$t('company.list.create.title')"
          @click="$router.push('/search')"
        />
      </slot>
    </div>
  </div>
</template>

<script setup lang="ts">
import Button from '@/components/ui/Button.vue'
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
    ? t('company.empty.noResults.title', 'No companies found')
    : t('company.empty.noCompanies.title', 'No companies yet')
})

const description = computed(() => {
  if (props.type === 'no-results') {
    return props.searchQuery
      ? t(
          'company.empty.noResults.description',
          'No companies match "{query}". Try adjusting your search terms.',
          { query: props.searchQuery },
        )
      : t('company.empty.noResults.descriptionNoQuery', 'Try adjusting your search terms')
  }
  return t(
    'company.empty.noCompanies.description',
    'Get started by creating your first company to track and manage your business relationships.',
  )
})
</script>
