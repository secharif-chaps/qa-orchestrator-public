<template>
  <div v-if="hasData" class="bg-base-200 rounded-card border-primary-stroke space-y-4 border p-5">
    <h3 class="text-base font-semibold">
      {{ t('screen.profile.sections.financial.identity.title') }}
    </h3>

    <div class="flex flex-wrap gap-4">
      <!-- Company Type Badge -->
      <div v-if="companyType?.value" class="flex items-center gap-2">
        <Tag
          :variant="companyType.value.toLowerCase() === 'public' ? 'info' : 'slate'"
          :label="companyType.value"
          size="sm"
          :icon="companyType.value.toLowerCase() === 'public' ? 'fa fa-landmark' : 'fa fa-lock'"
        />
        <Source :sourced-value="companyType" />
      </div>

      <!-- Ticker Symbol -->
      <div v-if="tickerSymbol?.value" class="flex items-center gap-3">
        <div class="flex flex-col">
          <span class="text-secondary/70 text-xs">
            {{ t('screen.profile.sections.financial.identity.ticker') }}
          </span>
          <span class="text-sm font-semibold">{{ tickerSymbol.value }}</span>
        </div>
        <Source :sourced-value="tickerSymbol" />
      </div>

      <!-- Stock Exchange -->
      <div v-if="stockExchange?.value" class="flex items-center gap-3">
        <div class="flex flex-col">
          <span class="text-secondary/70 text-xs">
            {{ t('screen.profile.sections.financial.identity.exchange') }}
          </span>
          <span class="text-sm font-semibold">{{ stockExchange.value }}</span>
        </div>
        <Source :sourced-value="stockExchange" />
      </div>

      <!-- Currency -->
      <div v-if="currency?.value" class="flex items-center gap-3">
        <div class="flex flex-col">
          <span class="text-secondary/70 text-xs">
            {{ t('screen.profile.sections.financial.identity.currency') }}
          </span>
          <span class="text-sm font-semibold">{{ currency.value }}</span>
        </div>
        <Source :sourced-value="currency" />
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Source from '@/components/company/Source.vue'
import Tag from '@/components/ui/Tag.vue'
import type { SourcedValue } from '@/types/company'

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
