<template>
  <Accordion.Root type="multiple" :default-value="defaultExpanded" as="template">
    <Accordion.Item
      v-for="[category, productList] in categories"
      :key="category"
      v-slot="{ open }"
      class="border-primary-light-stroke overflow-clip rounded-sm border"
      :value="category"
    >
      <Accordion.Header class="flex">
        <Accordion.Trigger
          class="gap-2xs bg-primary-lightest p-xs flex w-full cursor-pointer items-center"
        >
          <div class="gap-2xs flex min-w-0 grow items-center">
            <Icon :icon="getCategoryIcon(category)" class="shrink-0 text-base" />
            <span class="text-neutral-black-font text-lg font-semibold">
              {{ formatCategoryName(category) }}
            </span>
          </div>
          <span class="text-neutral-black-font shrink-0 text-sm">
            {{ t('screen.products.categoryCount', { count: productList.length }) }}
          </span>
          <Button
            class="transition-transform"
            :class="{ '-rotate-180': open }"
            icon="fa-chevron-down"
            variant="tertiary"
            size="sm"
            :aria-label="open ? 'Collapse' : 'Expand'"
          />
        </Accordion.Trigger>
      </Accordion.Header>
      <Transition
        enter-active-class="grid transition-[grid-template-rows,opacity] duration-300 ease-[cubic-bezier(0.22,1,0.36,1)]"
        leave-active-class="grid transition-[grid-template-rows,opacity] duration-300 ease-[cubic-bezier(0.22,1,0.36,1)]"
        enter-from-class="grid-rows-[0fr] opacity-0"
        enter-to-class="grid-rows-[1fr] opacity-100"
        leave-from-class="grid-rows-[1fr] opacity-100"
        leave-to-class="grid-rows-[0fr] opacity-0"
      >
        <div v-show="open" class="border-primary-light-stroke grid grid-rows-[1fr] border-t">
          <div class="overflow-hidden">
            <div class="gap-md px-xs py-md flex flex-col">
              <!-- Product tags -->
              <div class="gap-2xs flex flex-wrap">
                <Tag
                  v-for="product in productList"
                  :key="product"
                  variant="primary"
                  size="sm"
                  rounded
                >
                  {{ product }}
                </Tag>
              </div>

              <!-- Associated brands section -->
              <div
                v-if="brandsForCategory(category).length > 0"
                class="gap-2xs border-primary-lighter-stroke pt-xs flex flex-col border-t"
              >
                <span class="text-neutral-black-font text-base">
                  {{ t('screen.products.associatedBrands') }}
                </span>
                <div class="gap-2xs flex flex-wrap">
                  <Tag
                    v-for="brand in brandsForCategory(category)"
                    :key="getSourcedValue(brand)"
                    color="yellow"
                    size="sm"
                  >
                    <div class="gap-3xs flex items-center">
                      <span>{{ stripParenthesisSuffix(getSourcedValue(brand)) }}</span>
                      <Source :sourced-value="brand" />
                    </div>
                  </Tag>
                </div>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Accordion.Item>
  </Accordion.Root>
</template>

<script setup lang="ts">
import Source from '@/components/company/Source.vue'
import { getSourcedValue } from '@/components/helpers/sourcedValues'
import type { SourcedValue } from '@/types/company'
import { Button, Icon, Tag } from '@owlint/feathers-vue'
import { Accordion } from 'reka-ui/namespaced'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

interface Props {
  categories: [string, string[]][]
  partnerBrands?: SourcedValue<string>[]
  privateLabels?: SourcedValue<string>[]
}

const { categories, partnerBrands = [], privateLabels = [] } = defineProps<Props>()
const { t } = useI18n()

// Expand the first category by default
const defaultExpanded = computed(() => {
  if (categories.length === 0) return []
  return [categories[0][0]]
})

// Combine partner brands and private labels for display
const allBrands = computed<SourcedValue<string>[]>(() => {
  const brands: SourcedValue<string>[] = []
  for (const brand of partnerBrands) {
    if (brand.value) brands.push(brand)
  }
  for (const label of privateLabels) {
    if (label.value) brands.push(label)
  }
  return brands
})

// Show all brands under the first category only since we don't have per-category brand mapping
const brandsForCategory = (category: string): SourcedValue<string>[] => {
  if (categories.length > 0 && categories[0][0] === category) {
    return allBrands.value
  }
  return []
}

// Strip parenthesized suffix from brand names (e.g. "Brand (FR)" → "Brand")
const stripParenthesisSuffix = (value: string | undefined) => value?.split('(')[0]?.trim()

const formatCategoryName = (category: string) => {
  return category.replace(/([A-Z])/g, ' $1').replace(/^./, (str) => str.toUpperCase())
}

const getCategoryIcon = (category: string) => {
  const iconMap: Record<string, string> = {
    software: 'fa-laptop-code',
    hardware: 'fa-microchip',
    services: 'fa-handshake',
    consulting: 'fa-lightbulb',
    saas: 'fa-cloud',
    mobile: 'fa-mobile-alt',
    web: 'fa-globe',
    enterprise: 'fa-building',
    consumer: 'fa-users',
    healthcare: 'fa-heartbeat',
    finance: 'fa-chart-line',
    education: 'fa-graduation-cap',
    retail: 'fa-shopping-cart',
    gaming: 'fa-gamepad',
    analytics: 'fa-chart-bar',
    security: 'fa-shield-alt',
    ai: 'fa-robot',
    blockchain: 'fa-link',
    iot: 'fa-wifi',
  }

  const normalizedCategory = category.toLowerCase().replace(/\s+/g, '')
  return iconMap[normalizedCategory] || 'fa-tag'
}
</script>
