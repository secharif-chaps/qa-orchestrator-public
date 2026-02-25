<template>
  <div class="bg-base-300 flex min-h-screen items-center justify-center">
    <div class="text-center">
      <div class="mb-8">
        <h1 class="text-secondary text-6xl font-bold">403</h1>

        <!-- Token-specific error messages -->
        <div v-if="isTokenError">
          <h2 class="mt-4 text-2xl font-semibold">
            {{ tokenErrorTitle }}
          </h2>
          <p class="text-secondary mt-2">
            {{ tokenErrorMessage }}
          </p>

          <!-- Token status display -->
          <div
            v-if="errorModule"
            class="bg-base-200 mt-4 inline-flex items-center gap-2 rounded-lg px-4 py-2"
          >
            <i class="fa fa-coins text-secondary"></i>
            <span class="text-sm">
              <span class="font-medium capitalize">{{ errorModule }}</span> Module
              <span v-if="reason === 'module_disabled'" class="ml-2 text-red-600"
                >• {{ $t('errors.forbidden.token.status.disabled', 'Disabled') }}</span
              >
              <span v-else-if="reason === 'insufficient_tokens'" class="ml-2 text-red-600"
                >• {{ $t('errors.forbidden.token.status.noTokens', 'No Tokens') }}</span
              >
            </span>
          </div>
        </div>

        <!-- Regular permission error -->
        <div v-else>
          <h2 class="mt-4 text-2xl font-semibold">
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

      <div class="text-secondary mt-8 text-sm">
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
import { Button } from '@owlint/feathers-vue'

const route = useRoute()
const { t } = useI18n()

// Extract error details from query parameters
const reason = computed(() => route.query.reason as string)
const errorModule = computed(() => route.query.module as string)

const isTokenError = computed(
  () => reason.value === 'module_disabled' || reason.value === 'insufficient_tokens',
)

const tokenErrorTitle = computed(() => {
  if (reason.value === 'module_disabled') {
    return t('errors.forbidden.token.moduleDisabled', '{module} Disabled', {
      module: errorModule.value || 'Module',
    })
  }
  if (reason.value === 'insufficient_tokens') {
    return t('errors.forbidden.token.insufficientTokens', 'Insufficient Tokens')
  }
  return t('errors.forbidden.token.accessRestricted', 'Access Restricted')
})

const tokenErrorMessage = computed(() => {
  if (reason.value === 'module_disabled') {
    return t(
      'errors.forbidden.token.moduleDisabledMessage',
      'The {module} module has been disabled for your organization. Contact your administrator to enable this feature.',
      { module: errorModule.value || 'requested' },
    )
  }
  if (reason.value === 'insufficient_tokens') {
    return t(
      'errors.forbidden.token.insufficientTokensMessage',
      "You don't have enough tokens to access the {module} module. Contact your administrator to add more tokens.",
      { module: errorModule.value || 'requested' },
    )
  }
  return t('errors.forbidden.token.unavailable', 'This feature is currently unavailable.')
})
</script>
