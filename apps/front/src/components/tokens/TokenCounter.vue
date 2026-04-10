<template>
  <div class="relative">
    <!-- Main Counter Card -->
    <div
      class="from-bg1 to-bg2 border-primary-lighter-stroke rounded-xl border bg-gradient-to-br p-4 shadow-sm transition-all duration-200 hover:shadow-md"
    >
      <div class="flex items-center gap-3">
        <!-- Animated Token Icon -->
        <div class="relative">
          <div
            :class="tokenIconClasses"
            class="flex h-12 w-12 items-center justify-center rounded-xl text-lg font-bold transition-all duration-200 group-hover:scale-105"
          >
            <i :class="{ 'animate-spin': isLoading || isRefreshing }" class="fa fa-coins"></i>
          </div>

          <!-- Loading pulse overlay -->
          <div
            v-if="isLoading || isRefreshing"
            class="bg-primary/20 absolute inset-0 animate-pulse rounded-xl"
          ></div>
        </div>

        <!-- Content -->
        <div class="min-w-0 flex-1">
          <!-- Label -->
          <div class="mb-1 flex items-center gap-2">
            <span class="text-neutral-black-font text-xs font-medium tracking-wide uppercase">
              {{ label }}
            </span>
            <Button
              v-if="showRefresh"
              variant="tertiary"
              icon="fa fa-refresh"
              size="sm"
              :loading="isRefreshing"
              :disabled="isRefreshing"
              :title="$t('settings.tokens.refresh')"
              @click="$emit('refresh')"
            />
          </div>

          <!-- Token Count Display -->
          <div class="flex items-baseline gap-2">
            <span :class="tokenCountClasses" class="text-2xl font-bold tabular-nums">
              {{ displayCount }}
            </span>
            <span v-if="showLabel && !isLoading" class="text-neutral-black-font text-sm">
              {{ tokenLabel }}
            </span>
          </div>

          <!-- Company Creation Equivalence -->
          <div v-if="showCompanyEquivalence && !isLoading" class="mt-1.5">
            <span class="text-neutral-black-font text-xs">
              {{ companyEquivalenceText }}
            </span>
          </div>

          <!-- Loading State -->
          <div
            v-if="isLoading"
            class="text-neutral-black-font mt-2 flex items-center gap-2 text-xs"
          >
            <div class="bg-primary/60 h-2 w-2 animate-pulse rounded-full"></div>
            {{ $t('settings.tokens.loading') }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useTokenConfig } from '@/composables/useGlobalTokens'
import { Button } from '@owlint/feathers-vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const { tokensPerCompany } = useTokenConfig()

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
  if (props.tokenCount === 1) return t('settings.tokens.token')
  return t('settings.tokens.credits')
})

const companyEquivalenceText = computed(() => {
  const companiesCount = Math.floor(props.tokenCount / tokensPerCompany.value)

  if (companiesCount === 0) {
    return t('settings.tokens.companyEquivalence.none')
  }

  if (companiesCount === 1) {
    return t('settings.tokens.companyEquivalence.singular')
  }

  return t('settings.tokens.companyEquivalence.plural', { count: companiesCount })
})

const tokenIconClasses = computed(() => {
  if (props.tokenCount === 0) return 'bg-warning/10 text-warning'
  if (props.tokenCount < tokensPerCompany.value) return 'bg-warning/15 text-warning'
  return 'bg-success/10 text-success'
})

const tokenCountClasses = computed(() => {
  if (props.tokenCount === 0) return 'text-warning'
  if (props.tokenCount < tokensPerCompany.value) return 'text-warning'
  return 'text-success'
})
</script>
