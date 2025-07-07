<template>
  <div class="bg-bg1 border border-border-2 rounded-lg">
    <div class="px-6 py-4 border-b border-border-2">
      <h2 class="text-lg font-semibold">{{ $t('account.security.twoFactor.title') }}</h2>
      <p class="text-sm text-secondary mt-1">{{ $t('account.security.twoFactor.description') }}</p>
    </div>
    <div class="px-6 py-6">
      <div class="space-y-4">
        <!-- Authenticator App -->
        <div class="flex items-center justify-between p-4 border border-slate-200 dark:border-slate-700 rounded-lg">
          <div class="flex items-center space-x-3">
            <div class="flex-shrink-0">
              <i class="fas fa-mobile-alt text-secondary"></i>
            </div>
            <div>
              <h3 class="text-sm font-medium">{{ $t('account.security.twoFactor.authenticator.title') }}</h3>
              <p class="text-sm text-secondary">{{ $t('account.security.twoFactor.authenticator.description') }}</p>
            </div>
          </div>
          <div class="flex items-center space-x-2">
            <OBadge 
              :color="twoFactorEnabled ? 'green' : 'slate'" 
              :text="twoFactorEnabled ? $t('account.security.status.enabled') : $t('account.security.status.disabled')"
            >
              {{ twoFactorEnabled ? $t('account.security.status.enabled') : $t('account.security.status.disabled') }}
            </OBadge>
            <OButton 
              :label="twoFactorEnabled ? $t('account.security.actions.disable') : $t('account.security.actions.setup')"
              type="secondary"
              :color="twoFactorEnabled ? 'red' : 'primary'"
              size="sm"
              @click="handleToggleTwoFactor"
            />
          </div>
        </div>

        <!-- Security Keys -->
        <div class="flex items-center justify-between p-4 border border-border-2 rounded-lg">
          <div class="flex items-center space-x-3">
            <div class="flex-shrink-0">
              <i class="fas fa-key text-secondary"></i>
            </div>
            <div>
              <h3 class="text-sm font-medium">{{ $t('account.security.twoFactor.securityKeys.title') }}</h3>
              <p class="text-sm text-secondary">{{ $t('account.security.twoFactor.securityKeys.description') }}</p>
            </div>
          </div>
          <div class="flex items-center space-x-2">
            <OBadge 
              :color="securityKeysCount > 0 ? 'green' : 'slate'" 
              :text="securityKeysCount > 0 ? `${securityKeysCount} ${$t('account.security.twoFactor.securityKeys.count')}` : $t('account.security.status.disabled')"
            >
              {{ securityKeysCount > 0 ? `${securityKeysCount} ${$t('account.security.twoFactor.securityKeys.count')}` : $t('account.security.status.disabled') }}
            </OBadge>
            <OButton 
              :label="$t('account.security.actions.manage')"
              type="secondary"
              color="primary"
              size="sm"
              @click="handleManageSecurityKeys"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { OBadge, OButton } from '@owlint/feathers-vue'

interface Props {
  twoFactorEnabled: boolean
  securityKeysCount: number
}

const props = defineProps<Props>()

const emit = defineEmits<{
  toggleTwoFactor: []
  manageSecurityKeys: []
}>()

const handleToggleTwoFactor = () => {
  emit('toggleTwoFactor')
}

const handleManageSecurityKeys = () => {
  emit('manageSecurityKeys')
}
</script>