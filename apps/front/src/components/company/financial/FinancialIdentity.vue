<template>
  <div
    v-if="hasData"
    class="bg-primary-lightest rounded-card border-primary-lighter-stroke space-y-4 border p-6"
  >
    <h3 class="text-base font-semibold">
      {{ t('screen.profile.sections.financial.identity.title') }}
    </h3>

    <div class="flex flex-wrap gap-4">
      <!-- Company Type Badge -->
      <div v-if="companyType?.value" class="flex items-center gap-2">
        <Tag
          :color="companyType.value.toLowerCase() === 'public' ? 'indigo' : 'yellow'"
          size="sm"
          :icon="companyType.value.toLowerCase() === 'public' ? 'fa-landmark' : 'fa-lock'"
          class="capitalize"
        >
          {{ companyType.value }}
          <Source :sourced-value="companyType" />
        </Tag>
      </div>

      <!-- Ticker Symbol -->
      <div v-if="tickerSymbol?.value" class="flex items-center gap-3">
        <Tag size="sm">
          <div class="gap-2xs flex items-center">
            <span class="text-neutral-black-font text-xs">
              {{ t('screen.profile.sections.financial.identity.ticker') }}
            </span>
            <span class="text-sm font-semibold">{{ tickerSymbol.value }}</span>
            <Source :sourced-value="tickerSymbol" />
          </div>
        </Tag>
      </div>

      <!-- Stock Exchange -->
      <div v-if="stockExchange?.value" class="flex items-center gap-3">
        <Tag size="sm">
          <div class="gap-2xs flex items-center">
            <span class="text-neutral-black-font text-xs">
              {{ t('screen.profile.sections.financial.identity.exchange') }}
            </span>
            <span class="text-sm font-semibold">{{ stockExchange.value }}</span>
            <Source :sourced-value="stockExchange" />
          </div>
        </Tag>
      </div>

      <!-- Currency -->
      <div v-if="currency?.value" class="flex items-center gap-3">
        <Tag size="sm">
          <div class="gap-2xs flex items-center">
            <span class="text-neutral-black-font text-xs">
              {{ t('screen.profile.sections.financial.identity.currency') }}
            </span>
            <span class="text-sm font-semibold">{{ currency.value }}</span>
            <Source :sourced-value="currency" />
          </div>
        </Tag>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import Source from '@/components/company/Source.vue'
import type { SourcedValue } from '@/types/company'
import { Tag } from '@owlint/feathers-vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

interface Props {
  companyType: SourcedValue<string> | null
  tickerSymbol: SourcedValue<string> | null
  stockExchange: SourcedValue<string> | null
  currency: SourcedValue<string> | null
}

const { companyType, tickerSymbol, stockExchange, currency } = defineProps<Props>()

const hasData = computed(
  () => companyType?.value || tickerSymbol?.value || stockExchange?.value || currency?.value,
)
</script>
