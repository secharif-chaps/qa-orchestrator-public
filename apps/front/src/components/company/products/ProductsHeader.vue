<template>
  <div class="gap-sm flex items-center justify-between">
    <div class="basis-1/2">
      <h2 class="text-lg font-bold">{{ t('screen.products.header.title') }}</h2>
      <p>
        {{
          t('screen.products.header.summary', {
            total: totalProductCount,
            categories: categoryCount,
          })
        }}
      </p>
    </div>
    <div class="gap-xs flex basis-1/2 items-center">
      <Searchbar
        id="product-search"
        v-model="searchQuery"
        class="grow"
        size="sm"
        :placeholder="t('screen.products.search.placeholder')"
      />
      <Button variant="tertiary" size="sm" icon="fa-tag" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { Button, Searchbar } from '@owlint/feathers-vue'
import { useI18n } from 'vue-i18n'

interface Props {
  totalProductCount: number
  categoryCount: number
}

defineProps<Props>()

const searchQuery = defineModel<string>('searchQuery', { required: true })
const { t } = useI18n()
</script>
