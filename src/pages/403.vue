<template>
  <div class="min-h-screen flex items-center justify-center bg-bg3">
    <div class="text-center">
      <div class="mb-8">
        <h1 class="text-6xl font-bold text-primary">403</h1>
        
        <!-- Token-specific error messages -->
        <div v-if="isTokenError">
          <h2 class="text-2xl font-semibold mt-4">
            {{ tokenErrorTitle }}
          </h2>
          <p class="text-secondary mt-2">
            {{ tokenErrorMessage }}
          </p>
          
          <!-- Token status display -->
          <div v-if="errorModule" class="mt-4 inline-flex items-center gap-2 bg-bg2 px-4 py-2 rounded-lg">
            <i class="fa fa-coins text-primary"></i>
            <span class="text-sm">
              <span class="font-medium capitalize">{{ errorModule }}</span> Module
              <span v-if="reason === 'module_disabled'" class="text-red-600 ml-2">• Disabled</span>
              <span v-else-if="reason === 'insufficient_tokens'" class="text-red-600 ml-2">• No Tokens</span>
            </span>
          </div>
        </div>
        
        <!-- Regular permission error -->
        <div v-else>
          <h2 class="text-2xl font-semibold mt-4">
            {{ $t('errors.forbidden.title', 'Access Forbidden') }}
          </h2>
          <p class="text-secondary mt-2">
            {{ $t('errors.forbidden.message', "You don't have permission to access this page.") }}
          </p>
        </div>
      </div>

      <div class="space-x-4">
        <Button
          :label="$t('errors.forbidden.goHome', 'Go to Home')"
          variant="primary"
          @click="$router.push('/')"
        />
        <Button
          :label="$t('errors.forbidden.goBack', 'Go Back')"
          variant="secondary"
          @click="$router.back()"
        />
      </div>

      <div class="mt-8 text-sm text-secondary">
        <p>
          {{
            $t(
              'errors.forbidden.contact',
              'If you believe this is an error, please contact your administrator.',
            )
          }}
        </p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import Button from '@/components/ui/Button.vue'

const route = useRoute()
const { t } = useI18n()

// Extract error details from query parameters
const reason = computed(() => route.query.reason as string)
const errorModule = computed(() => route.query.module as string)

const isTokenError = computed(() => 
  reason.value === 'module_disabled' || reason.value === 'insufficient_tokens'
)

const tokenErrorTitle = computed(() => {
  if (reason.value === 'module_disabled') {
    return `${errorModule.value || 'Module'} Disabled`
  }
  if (reason.value === 'insufficient_tokens') {
    return 'Insufficient Tokens'
  }
  return 'Access Restricted'
})

const tokenErrorMessage = computed(() => {
  if (reason.value === 'module_disabled') {
    return `The ${errorModule.value || 'requested'} module has been disabled for your workspace. Contact your administrator to enable this feature.`
  }
  if (reason.value === 'insufficient_tokens') {
    return `You don't have enough tokens to access the ${errorModule.value || 'requested'} module. Contact your administrator to add more tokens.`
  }
  return 'This feature is currently unavailable.'
})
</script>
