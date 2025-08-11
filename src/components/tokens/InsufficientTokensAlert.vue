<template>
  <Alert
    variant="warning"
    :title="$t('tokens.insufficientTitle', 'Out of Search Tokens')"
    :message="$t(
      'tokens.insufficientMessage',
      'You need tokens to search for companies. Contact your administrator to get more tokens and continue searching.',
    )"
    icon="fa fa-exclamation-triangle"
    decoration-icon="fa fa-coins"
    :show="show"
    :dismissible="true"
    :dismiss-label="$t('common.dismiss', 'Dismiss')"
    @dismiss="$emit('dismiss')"
  >
    <template #status>
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
    </template>

    <template #actions>
      <Button
        v-if="showContactAdmin"
        variant="primary"
        icon="fa fa-user-tie"
        :label="$t('tokens.contactAdmin', 'Contact Admin')"
        size="sm"
        @click="$emit('contact-admin')"
      />

      <Button
        v-if="showRefresh"
        variant="secondary"
        icon="fa fa-refresh"
        :label="$t('tokens.refresh', 'Refresh')"
        size="sm"
        :loading="isRefreshing"
        :disabled="isRefreshing"
        @click="$emit('refresh')"
      />
    </template>
  </Alert>
</template>

<script setup lang="ts">
import Alert from '@/components/ui/Alert.vue'
import Button from '@/components/ui/Button.vue'
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
