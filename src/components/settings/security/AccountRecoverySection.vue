<template>
  <div class="bg-base-100 border border-primary-stroke rounded-card">
    <div class="px-6 py-4 border-b border-primary-stroke">
      <h2 class="text-lg font-semibold">{{ $t('settings.security.recovery.title') }}</h2>
      <p class="text-sm text-secondary mt-1">
        {{ $t('settings.security.recovery.description') }}
      </p>
    </div>
    <div class="px-6 py-6">
      <div class="flex flex-col gap-4">
        <!-- Backup Codes -->
        <div class="flex items-center justify-between p-4 border border-primary-stroke rounded-lg">
          <div class="flex items-center gap-3">
            <div
              class="w-10 h-10 rounded-lg flex items-center justify-center"
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
              <p class="text-sm text-secondary">
                {{ $t('settings.security.recovery.backupCodes.description') }}
              </p>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <Tag
              :variant="backupCodesGenerated ? 'success' : 'warning'"
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
        <div class="flex items-center justify-between p-4 border border-primary-stroke rounded-lg">
          <div class="flex items-center gap-3">
            <div
              class="w-10 h-10 rounded-lg flex items-center justify-center"
              :class="
                recoveryEmail
                  ? 'bg-success-light text-success-light-content'
                  : 'bg-base-200 text-secondary'
              "
            >
              <i class="fas fa-envelope"></i>
            </div>
            <div>
              <h3 class="text-sm font-medium">
                {{ $t('settings.security.recovery.email.title') }}
              </h3>
              <p class="text-sm text-secondary">
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
import Tag from '@/components/ui/Tag.vue'
import { Button } from '@owlint/feathers-vue'

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
