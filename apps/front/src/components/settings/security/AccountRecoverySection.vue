<template>
  <div class="border-primary-lighter-stroke rounded-card border bg-white">
    <div class="border-primary-lighter-stroke border-b px-6 py-4">
      <h2 class="text-lg font-semibold">{{ $t('settings.security.recovery.title') }}</h2>
      <p class="text-neutral-black-font mt-1 text-sm">
        {{ $t('settings.security.recovery.description') }}
      </p>
    </div>
    <div class="px-6 py-6">
      <div class="flex flex-col gap-4">
        <!-- Backup Codes -->
        <div
          class="border-primary-lighter-stroke flex items-center justify-between rounded-sm border p-4"
        >
          <div class="flex items-center gap-3">
            <div
              class="flex h-10 w-10 items-center justify-center rounded-sm"
              :class="
                backupCodesGenerated
                  ? 'bg-success-light text-success-light-content'
                  : 'bg-warning-light text-warning-light-content'
              "
            >
              <i class="fas fa-shield-alt"></i>
            </div>
            <div>
              <h3 class="text-sm font-medium">
                {{ $t('settings.security.recovery.backupCodes.title') }}
              </h3>
              <p class="text-neutral-black-font text-sm">
                {{ $t('settings.security.recovery.backupCodes.description') }}
              </p>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <Tag
              :intent="backupCodesGenerated ? 'success' : 'warning'"
              :label="
                backupCodesGenerated
                  ? $t('settings.security.status.generated')
                  : $t('settings.security.status.notGenerated')
              "
            />
            <Button
              :label="
                backupCodesGenerated
                  ? $t('settings.security.actions.regenerate')
                  : $t('settings.security.actions.generate')
              "
              variant="secondary"
              size="sm"
              @click="handleGenerateBackupCodes"
            />
          </div>
        </div>

        <!-- Recovery Email -->
        <div
          class="border-primary-lighter-stroke flex items-center justify-between rounded-sm border p-4"
        >
          <div class="flex items-center gap-3">
            <div
              class="flex h-10 w-10 items-center justify-center rounded-sm"
              :class="
                recoveryEmail
                  ? 'bg-success-light text-success-light-content'
                  : 'bg-primary-lightest text-neutral-black-font'
              "
            >
              <i class="fas fa-envelope"></i>
            </div>
            <div>
              <h3 class="text-sm font-medium">
                {{ $t('settings.security.recovery.email.title') }}
              </h3>
              <p class="text-neutral-black-font text-sm">
                {{ recoveryEmail || $t('settings.security.recovery.email.notSet') }}
              </p>
            </div>
          </div>
          <Button
            :label="
              recoveryEmail
                ? $t('settings.security.actions.update')
                : $t('settings.security.actions.add')
            "
            variant="secondary"
            size="sm"
            @click="handleUpdateRecoveryEmail"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Button, Tag } from '@owlint/feathers-vue'

defineProps<{
  backupCodesGenerated: boolean
  recoveryEmail: string
}>()

const emit = defineEmits<{
  generateBackupCodes: []
  updateRecoveryEmail: []
}>()

function handleGenerateBackupCodes() {
  emit('generateBackupCodes')
}

function handleUpdateRecoveryEmail() {
  emit('updateRecoveryEmail')
}
</script>
