<template>
  <div class="relative">
    <!-- Main Counter Card -->
    <div
      class="bg-gradient-to-br from-bg1 to-bg2 border border-border-2 rounded-xl p-4 shadow-sm hover:shadow-md transition-all duration-200"
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
          <!-- Module Label -->
          <div class="flex items-center gap-2 mb-1">
            <span class="text-xs font-medium text-secondary uppercase tracking-wide">
              {{ module || 'Screen' }} Module
            </span>
            <Button
              v-if="showRefresh"
              variant="ghost-primary"
              icon="fa fa-refresh"
              icon-only
              size="sm"
              :loading="isRefreshing"
              :disabled="isRefreshing"
              :title="$t('tokens.refresh', 'Refresh token count')"
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

          <!-- Status Badge -->
          <!-- <div v-if="showStatus && !isLoading" class="mt-2">
            <div :class="statusBadgeClasses" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-full">
              <div :class="statusDotClasses" class="w-1.5 h-1.5 rounded-full"></div>
              {{ statusText }}
            </div>
          </div> -->

          <!-- Loading State -->
          <div v-if="isLoading" class="mt-2 flex items-center gap-2 text-xs text-secondary">
            <div class="w-2 h-2 bg-primary/60 rounded-full animate-pulse"></div>
            Loading token data...
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { ModuleName } from '@/types/tokens'
import Button from '@/components/ui/Button.vue'

interface Props {
  module?: ModuleName
  tokenCount: number
  isEnabled?: boolean
  isLoading?: boolean
  isRefreshing?: boolean
  showLabel?: boolean
  showRefresh?: boolean
  showStatus?: boolean
  variant?: 'default' | 'compact' | 'detailed'
}

const props = withDefaults(defineProps<Props>(), {
  isEnabled: true,
  isLoading: false,
  isRefreshing: false,
  showLabel: false,
  showRefresh: false,
  showStatus: false,
  variant: 'default',
})

defineEmits<{
  refresh: []
}>()

// Computed properties
const displayCount = computed(() => {
  if (props.isLoading) return '...'
  if (!props.isEnabled) return '--'
  return props.tokenCount.toLocaleString()
})

const tokenLabel = computed(() => {
  if (props.tokenCount === 1) return 'token'
  return 'tokens'
})

const tokenIconClasses = computed(() => {
  if (!props.isEnabled) return 'bg-secondary/10 text-secondary'
  if (props.tokenCount === 0) return 'bg-warning/10 text-warning'
  if (props.tokenCount < 10) return 'bg-warning/15 text-warning'
  return 'bg-success/10 text-success'
})

const tokenCountClasses = computed(() => {
  if (!props.isEnabled) return 'text-secondary'
  if (props.tokenCount === 0) return 'text-warning'
  if (props.tokenCount < 10) return 'text-warning'
  return 'text-success'
})

const statusBadgeClasses = computed(() => {
  if (!props.isEnabled) return 'bg-secondary/10 text-secondary'
  if (props.tokenCount === 0) return 'bg-warning/10 text-warning'
  if (props.tokenCount < 10) return 'bg-warning/10 text-warning'
  return 'bg-success/10 text-success'
})

const statusDotClasses = computed(() => {
  if (!props.isEnabled) return 'bg-secondary'
  if (props.tokenCount === 0) return 'bg-warning'
  if (props.tokenCount < 10) return 'bg-warning'
  return 'bg-success'
})

const statusText = computed(() => {
  if (!props.isEnabled) return 'Disabled'
  if (props.tokenCount === 0) return 'No tokens'
  if (props.tokenCount < 10) return 'Low'
  return 'Active'
})
</script>
