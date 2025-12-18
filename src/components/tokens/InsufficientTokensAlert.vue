<template>
  <div v-if="show" class="flex flex-col gap-4">
    <Alert
      variant="warning"
      :title="$t('tokens.insufficientTitle', 'Out of Search Tokens')"
      :description="
        $t(
          'tokens.insufficientMessage',
          'You need tokens to search for companies. Contact your administrator to get more tokens and continue searching.',
        )
      "
      icon="fa-exclamation-triangle"
    />

    <!-- Token counter and actions -->
    <div class="flex items-center justify-between bg-warning-light border border-warning-stroke rounded-lg px-4 py-3">
      <div class="flex items-center gap-3">
        <i class="fa fa-coins text-warning"></i>
        <span class="text-sm font-medium text-warning-light-content">Current tokens:</span>
        <span class="text-xl font-bold text-warning-light-content">{{ currentTokens }}</span>
      </div>

      <div class="flex items-center gap-2">
        <Button
          v-if="showContactAdmin"
          variant="primary"
          icon="fa-user-tie"
          :label="$t('tokens.contactAdmin', 'Contact Admin')"
          size="sm"
          @click="$emit('contact-admin')"
        />

        <Button
          v-if="showRefresh"
          variant="secondary"
          icon="fa-refresh"
          :label="$t('tokens.refresh', 'Refresh')"
          size="sm"
          :loading="isRefreshing"
          :disabled="isRefreshing"
          @click="$emit('refresh')"
        />

        <Button
          variant="tertiary"
          icon="fa-times"
          size="sm"
          @click="$emit('dismiss')"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Alert, Button } from '@owlint/feathers-vue'
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
