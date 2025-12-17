<template>
  <div class="relative">
    <!-- Main Counter Card -->
    <div
      class="bg-gradient-to-br from-bg1 to-bg2 border border-primary-stroke rounded-xl p-4 shadow-sm hover:shadow-md transition-all duration-200"
    >
      <div class="flex items-center gap-3">
        <!-- Animated Token Icon -->
        <div class="relative">
          <div
            :class="tokenIconClasses"
            class="w-12 h-12 rounded-xl flex items-center justify-center text-lg font-bold transition-all duration-200 group-hover:scale-105"
          >
            <i :class="{ 'animate-spin': isLoading || isRefreshing }" class="fa fa-coins"></i>
          </div>

          <!-- Loading pulse overlay -->
          <div
            v-if="isLoading || isRefreshing"
            class="absolute inset-0 rounded-xl bg-primary/20 animate-pulse"
          ></div>
        </div>

        <!-- Content -->
        <div class="flex-1 min-w-0">
          <!-- Label -->
          <div class="flex items-center gap-2 mb-1">
            <span class="text-xs font-medium text-secondary uppercase tracking-wide">
              {{ label }}
            </span>
            <Button
              v-if="showRefresh"
              variant="tertiary"
              icon="fa fa-refresh"
              size="sm"
              :loading="isRefreshing"
              :disabled="isRefreshing"
              :title="$t('tokens.refresh')"
              @click="$emit('refresh')"
            />
          </div>

          <!-- Token Count Display -->
          <div class="flex items-baseline gap-2">
            <span :class="tokenCountClasses" class="text-2xl font-bold tabular-nums">
              {{ displayCount }}
            </span>
            <span v-if="showLabel && !isLoading" class="text-sm text-secondary">
              {{ tokenLabel }}
            </span>
          </div>

          <!-- Company Creation Equivalence -->
          <div v-if="showCompanyEquivalence && !isLoading" class="mt-1.5">
            <span class="text-xs text-secondary">
              {{ companyEquivalenceText }}
            </span>
          </div>

          <!-- Loading State -->
          <div v-if="isLoading" class="mt-2 flex items-center gap-2 text-xs text-secondary">
            <div class="w-2 h-2 bg-primary/60 rounded-full animate-pulse"></div>
            {{ $t('tokens.loading', 'Loading token data...') }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Button } from '@owlint/feathers-vue'

const { t } = useI18n()

// Token cost for company creation - 35 tokens per company
const TOKENS_PER_COMPANY = 35

interface Props {
  tokenCount: number
  label?: string
  isLoading?: boolean
  isRefreshing?: boolean
  showLabel?: boolean
  showRefresh?: boolean
  showCompanyEquivalence?: boolean
  variant?: 'default' | 'compact' | 'detailed'
}

const props = withDefaults(defineProps<Props>(), {
  label: 'Token Balance',
  isLoading: false,
  isRefreshing: false,
  showLabel: false,
  showRefresh: false,
  showCompanyEquivalence: true,
  variant: 'default',
})

defineEmits<{
  refresh: []
}>()

// Computed properties
const displayCount = computed(() => {
  if (props.isLoading) return '...'
  return props.tokenCount.toLocaleString()
})

const tokenLabel = computed(() => {
  if (props.tokenCount === 1) return t('tokens.token')
  return t('tokens.credits')
})

const companyEquivalenceText = computed(() => {
  const companiesCount = Math.floor(props.tokenCount / TOKENS_PER_COMPANY)

  if (companiesCount === 0) {
    return t('tokens.companyEquivalence.none', 'Not enough for company creation')
  }

  if (companiesCount === 1) {
    return t('tokens.companyEquivalence.singular', 'Enough for 1 company')
  }

  return t('tokens.companyEquivalence.plural', { count: companiesCount })
})

const tokenIconClasses = computed(() => {
  if (props.tokenCount === 0) return 'bg-warning/10 text-warning'
  if (props.tokenCount < TOKENS_PER_COMPANY) return 'bg-warning/15 text-warning'
  return 'bg-success/10 text-success'
})

const tokenCountClasses = computed(() => {
  if (props.tokenCount === 0) return 'text-warning'
  if (props.tokenCount < TOKENS_PER_COMPANY) return 'text-warning'
  return 'text-success'
})
</script>
