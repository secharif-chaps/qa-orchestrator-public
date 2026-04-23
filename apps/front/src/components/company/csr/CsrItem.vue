<template>
  <div class="bg-primary-lightest gap-3xs p-xs flex flex-col rounded-md">
    <div class="gap-2xs flex items-start">
      <p class="flex-1 text-base leading-3">{{ item.value }}</p>
      <Source v-if="item.source" :source="item.source" class="shrink-0" />
    </div>
    <div>
      <Tag :icon="categoryIcon" color="almond" size="xs">
        {{ t('screen.profile.sections.csr.categoryLabel', { category: item.category }) }}
      </Tag>
    </div>
  </div>
</template>

<script setup lang="ts">
import Source from '@/components/company/Source.vue'
import type { CsrFlatItem } from '@/components/company/csr/types'
import { Tag } from '@owlint/feathers-vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

interface Props {
  item: CsrFlatItem
}

const { item } = defineProps<Props>()

const { t } = useI18n()

const CATEGORY_ICONS: Record<string, string> = {
  responsibility: 'fa-file-alt',
  responsibility_initiatives: 'fa-handshake',
  charity: 'fa-heart',
  sustainability: 'fa-leaf',
  community: 'fa-users',
  diversity: 'fa-people-group',
  ethics: 'fa-scale-balanced',
  awards: 'fa-award',
}

const categoryIcon = computed(() => CATEGORY_ICONS[item.category])
</script>
