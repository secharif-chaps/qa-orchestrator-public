<template>
  <div class="bg-primary-lightest p-xs flex flex-col gap-1 rounded-xl">
    <div class="flex items-start justify-between">
      <Source v-if="item.source" :source="item.source" />
      <span v-else />
    </div>
    <p class="text-sm">{{ item.value }}</p>
    <Tag
      :label="
        t('screen.profile.sections.press.categories.categoryLabel', { category: item.category })
      "
      :icon="CATEGORY_CONFIG[item.category].icon"
      color="almond"
      size="xs"
      class="self-start"
    />
  </div>
</template>

<script setup lang="ts">
import Source from '@/components/company/Source.vue'
import { CATEGORY_CONFIG, type PressItemWithCategory } from '@/types/press'
import { Tag } from '@owlint/feathers-vue'
import { useI18n } from 'vue-i18n'

interface Props {
  item: PressItemWithCategory
}

defineProps<Props>()

const { t } = useI18n()
</script>
