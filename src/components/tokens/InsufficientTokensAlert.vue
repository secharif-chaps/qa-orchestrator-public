<template>
  <div
    v-if="show"
    class="relative overflow-hidden rounded-xl border border-border-2 bg-gradient-to-r from-bg1 to-bg2 mb-6"
  >
    <!-- Background decoration -->
    <div class="absolute top-0 right-0 w-32 h-32 opacity-[0.03] dark:opacity-[0.08]">
      <i class="fa fa-coins text-6xl text-warning"></i>
    </div>

    <!-- Content -->
    <div class="relative p-6">
      <div class="flex items-start gap-4">
        <!-- Icon -->
        <div class="flex-shrink-0">
          <div class="w-12 h-12 rounded-full bg-warning/10 flex items-center justify-center">
            <i class="fa fa-exclamation-triangle text-warning text-lg"></i>
          </div>
        </div>

        <!-- Main content -->
        <div class="flex-1 min-w-0">
          <div class="flex items-start justify-between">
            <div>
              <h3 class="text-lg font-semibold text-base mb-1">
                {{ $t('tokens.insufficientTitle', 'Out of Search Tokens') }}
              </h3>
              <p class="text-secondary text-sm leading-relaxed mb-4">
                {{
                  $t(
                    'tokens.insufficientMessage',
                    'You need tokens to search for companies. Contact your administrator to get more tokens and continue searching.',
                  )
                }}
              </p>
            </div>

            <!-- Close button -->
            <button
              @click="$emit('dismiss')"
              class="text-secondary hover:text-base transition-colors p-1 ml-4"
              :title="$t('common.dismiss', 'Dismiss')"
            >
              <i class="fa fa-times"></i>
            </button>
          </div>

          <!-- Actions Row with Token Counter -->
          <div class="flex items-center justify-between">
            <!-- Token counter -->
            <div
              class="inline-flex items-center gap-3 bg-bg3 rounded-lg px-4 py-2 border border-border-1"
            >
              <div class="flex items-center gap-2">
                <i class="fa fa-coins text-warning"></i>
                <span class="text-sm font-medium text-secondary">Current tokens:</span>
              </div>
              <div class="text-2xl font-bold text-warning">{{ currentTokens }}</div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-3">
              <button
                v-if="showContactAdmin"
                @click="$emit('contact-admin')"
                class="bg-primary hover:bg-primary/80 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2"
              >
                <i class="fa fa-user-tie"></i>
                {{ $t('tokens.contactAdmin', 'Contact Admin') }}
              </button>

              <button
                v-if="showRefresh"
                @click="$emit('refresh')"
                :disabled="isRefreshing"
                class="bg-bg3 hover:bg-bg1 border border-border-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors disabled:opacity-50 flex items-center gap-2"
              >
                <i :class="{ 'animate-spin': isRefreshing }" class="fa fa-refresh"></i>
                {{ $t('tokens.refresh', 'Refresh') }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { ModuleName } from '@/types/tokens'

interface Props {
  module: ModuleName
  currentTokens: number
  requiredTokens: number
  show?: boolean
  showContactAdmin?: boolean
  showRefresh?: boolean
  isRefreshing?: boolean
}

withDefaults(defineProps<Props>(), {
  show: true,
  showContactAdmin: true,
  showRefresh: true,
  isRefreshing: false,
})

defineEmits<{
  'contact-admin': []
  refresh: []
  dismiss: []
}>()
</script>
