<template>
  <div class="bg-base-100 border border-primary-stroke rounded-lg">
    <div class="px-6 py-4 border-b border-primary-stroke">
      <h2 class="text-lg font-semibold">{{ $t('settings.security.twoFactor.title') }}</h2>
      <p class="text-sm text-primary-light-content mt-1">
        {{ $t('settings.security.twoFactor.description') }}
      </p>
    </div>
    <div class="px-6 py-6">
      <div class="space-y-4">
        <!-- Authenticator App -->
        <div
          class="flex items-center justify-between p-4 border border-slate-200 dark:border-slate-700 rounded-lg"
        >
          <div class="flex items-center space-x-3">
            <div class="flex-shrink-0">
              <i class="fas fa-mobile-alt text-primary-light-content"></i>
            </div>
            <div>
              <h3 class="text-sm font-medium">
                {{ $t('settings.security.twoFactor.authenticator.title') }}
              </h3>
              <p class="text-sm text-primary-light-content">
                {{ $t('settings.security.twoFactor.authenticator.description') }}
              </p>
            </div>
          </div>
          <div class="flex items-center space-x-2">
            <Badge
              :variant="twoFactorEnabled ? 'success' : 'slate'"
              :label="
                twoFactorEnabled
                  ? $t('settings.security.status.enabled')
                  : $t('settings.security.status.disabled')
              "
            />
            <Button
              :label="
                twoFactorEnabled
                  ? $t('settings.security.actions.disable')
                  : $t('settings.security.actions.setup')
              "
              variant="secondary"
              :color="twoFactorEnabled ? 'danger' : 'neutral'"
              size="sm"
              @click="handleToggleTwoFactor"
            />
          </div>
        </div>

        <!-- Security Keys -->
        <div class="flex items-center justify-between p-4 border border-primary-stroke rounded-lg">
          <div class="flex items-center space-x-3">
            <div class="flex-shrink-0">
              <i class="fas fa-key text-primary-light-content"></i>
            </div>
            <div>
              <h3 class="text-sm font-medium">
                {{ $t('settings.security.twoFactor.securityKeys.title') }}
              </h3>
              <p class="text-sm text-primary-light-content">
                {{ $t('settings.security.twoFactor.securityKeys.description') }}
              </p>
            </div>
          </div>
          <div class="flex items-center space-x-2">
            <Badge
              :variant="securityKeysCount > 0 ? 'success' : 'slate'"
              :label="
                securityKeysCount > 0
                  ? `${securityKeysCount} ${$t('settings.security.twoFactor.securityKeys.count')}`
                  : $t('settings.security.status.disabled')
              "
            />
            <Button
              :label="$t('settings.security.actions.manage')"
              variant="secondary"
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
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'

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
