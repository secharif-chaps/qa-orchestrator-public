<template>
  <div class="bg-primary-lighter flex min-h-screen items-center justify-center">
    <div class="text-center">
      <div class="mb-8">
        <h1 class="text-neutral-black-font text-6xl font-bold">
          {{ $t('common.errors.forbidden.code') }}
        </h1>

        <!-- Token-specific error messages -->
        <div v-if="isTokenError">
          <h2 class="mt-4 text-2xl font-semibold">
            {{ tokenErrorTitle }}
          </h2>
          <p class="text-neutral-black-font mt-2">
            {{ tokenErrorMessage }}
          </p>

          <!-- Token status display -->
          <div
            v-if="errorModule"
            class="bg-primary-lightest mt-4 inline-flex items-center gap-2 rounded-sm px-4 py-2"
          >
            <i class="fa fa-coins text-neutral-black-font"></i>
            <span class="text-sm">
              <span class="font-medium capitalize">{{
                t('common.errors.forbidden.token.moduleLabel', { module: errorModule })
              }}</span>
              <span v-if="reason === 'module_disabled'" class="ml-2 text-red-600">
                {{
                  $t('common.bulletPrefixed', {
                    value: $t('common.errors.forbidden.token.status.disabled'),
                  })
                }}
              </span>
              <span v-else-if="reason === 'insufficient_tokens'" class="ml-2 text-red-600">
                {{
                  $t('common.bulletPrefixed', {
                    value: $t('common.errors.forbidden.token.status.noTokens'),
                  })
                }}
              </span>
            </span>
          </div>
        </div>

        <!-- Regular permission error -->
        <div v-else>
          <h2 class="mt-4 text-2xl font-semibold">
            {{ $t('common.errors.forbidden.title') }}
          </h2>
          <p class="text-neutral-black-font mt-2">
            {{ $t('common.errors.forbidden.message') }}
          </p>
        </div>
      </div>

      <div class="space-x-4">
        <Button
          :label="$t('common.errors.forbidden.goHome')"
          variant="primary"
          @click="$router.push('/')"
        />
        <Button
          :label="$t('common.errors.forbidden.goBack')"
          variant="secondary"
          @click="$router.back()"
        />
      </div>

      <div class="text-neutral-black-font mt-8 text-sm">
        <p>
          {{ $t('common.errors.forbidden.contact') }}
        </p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Button } from '@owlint/feathers-vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'

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
    return t('common.errors.forbidden.token.moduleDisabled', {
      module: errorModule.value || 'Module',
    })
  }
  if (reason.value === 'insufficient_tokens') {
    return t('common.errors.forbidden.token.insufficientTokens')
  }
  return t('common.errors.forbidden.token.accessRestricted')
})

const tokenErrorMessage = computed(() => {
  if (reason.value === 'module_disabled') {
    return t('common.errors.forbidden.token.moduleDisabledMessage', {
      module: errorModule.value || 'requested',
    })
  }
  if (reason.value === 'insufficient_tokens') {
    return t('common.errors.forbidden.token.insufficientTokensMessage', {
      module: errorModule.value || 'requested',
    })
  }
  return t('common.errors.forbidden.token.unavailable')
})
</script>
