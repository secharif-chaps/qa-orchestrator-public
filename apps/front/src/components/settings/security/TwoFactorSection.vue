<template>
  <div class="border-primary-lighter-stroke rounded-card border bg-white">
    <div class="border-primary-lighter-stroke border-b px-6 py-4">
      <h2 class="text-lg font-semibold">{{ $t('settings.security.twoFactor.title') }}</h2>
      <p class="text-neutral-black-font mt-1 text-sm">
        {{ $t('settings.security.twoFactor.description') }}
      </p>
    </div>
    <div class="px-6 py-6">
      <div class="flex flex-col gap-4">
        <!-- Authenticator App -->
        <div
          class="border-primary-lighter-stroke flex items-center justify-between rounded-sm border p-4"
        >
          <div class="flex items-center gap-3">
            <div
              class="flex h-10 w-10 items-center justify-center rounded-sm"
              :class="
                twoFactorEnabled
                  ? 'bg-success text-success-content'
                  : 'bg-primary-lightest text-neutral-black-font'
              "
            >
              <i class="fas fa-mobile-alt"></i>
            </div>
            <div>
              <h3 class="text-sm font-medium">
                {{ $t('settings.security.twoFactor.authenticator.title') }}
              </h3>
              <p class="text-neutral-black-font text-sm">
                {{ $t('settings.security.twoFactor.authenticator.description') }}
              </p>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <Tag
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
        <div
          class="border-primary-lighter-stroke flex items-center justify-between rounded-sm border p-4"
        >
          <div class="flex items-center gap-3">
            <div
              class="flex h-10 w-10 items-center justify-center rounded-sm"
              :class="
                securityKeysCount > 0
                  ? 'bg-success text-success-content'
                  : 'bg-primary-lightest text-neutral-black-font'
              "
            >
              <i class="fas fa-key"></i>
            </div>
            <div>
              <h3 class="text-sm font-medium">
                {{ $t('settings.security.twoFactor.securityKeys.title') }}
              </h3>
              <p class="text-neutral-black-font text-sm">
                {{ $t('settings.security.twoFactor.securityKeys.description') }}
              </p>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <Tag
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
import Tag from '@/components/ui/Tag.vue'
import { Button } from '@owlint/feathers-vue'

defineProps<{
  twoFactorEnabled: boolean
  securityKeysCount: number
}>()

const emit = defineEmits<{
  toggleTwoFactor: []
  manageSecurityKeys: []
}>()

function handleToggleTwoFactor() {
  emit('toggleTwoFactor')
}

function handleManageSecurityKeys() {
  emit('manageSecurityKeys')
}
</script>
