<template>
  <div class="bg-bg1 border border-border-2 rounded-lg">
    <div class="px-6 py-4 border-b border-border-2">
      <h2 class="text-lg font-semibold">{{ $t('settings.security.recovery.title') }}</h2>
      <p class="text-sm text-secondary mt-1">{{ $t('settings.security.recovery.description') }}</p>
    </div>
    <div class="px-6 py-6">
      <div class="space-y-4">
        <!-- Backup Codes -->
        <div
          class="flex items-center justify-between p-4 border border-slate-200 dark:border-slate-700 rounded-lg"
        >
          <div class="flex items-center space-x-3">
            <div class="flex-shrink-0">
              <i class="fas fa-shield-alt text-secondary"></i>
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
          <div class="flex items-center space-x-2">
            <Badge
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
        <div
          class="flex items-center justify-between p-4 border border-slate-200 dark:border-slate-700 rounded-lg"
        >
          <div class="flex items-center space-x-3">
            <div class="flex-shrink-0">
              <i class="fas fa-envelope text-secondary"></i>
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
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'

interface Props {
  backupCodesGenerated: boolean
  recoveryEmail: string
}

const props = defineProps<Props>()

const emit = defineEmits<{
  generateBackupCodes: []
  updateRecoveryEmail: []
}>()

const handleGenerateBackupCodes = () => {
  emit('generateBackupCodes')
}

const handleUpdateRecoveryEmail = () => {
  emit('updateRecoveryEmail')
}
</script>
