<template>
  <div
    class="fixed inset-0 bg-base-100/20 backdrop-blur-sm flex items-center justify-center z-50"
    @click.self="$emit('close')"
  >
    <div class="bg-base-100 rounded-xl shadow-2xl border border-primary-stroke p-6 max-w-md w-full mx-4">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <h3 class="text-lg font-semibold">
          {{ t('settings.team.resetPassword.title', 'Password Reset Successful') }}
        </h3>
        <Button variant="tertiary" icon="fa fa-times" @click="$emit('close')" />
      </div>

      <!-- User Info -->
      <p class="text-sm text-secondary mb-4">
        {{ t('settings.team.resetPassword.description', 'Temporary password for') }}
        <span class="font-semibold">{{ memberDisplayName }}</span>
      </p>

      <!-- Password Display with Copy Button -->
      <div class="bg-base-200 border border-primary-stroke rounded-lg p-4 mb-6">
        <div class="flex items-center justify-between gap-3">
          <code class="text-lg font-mono font-semibold flex-1 truncate">{{ temporaryPassword }}</code>
          <Button variant="tertiary" icon="fa fa-copy" size="sm" @click="copyPassword" />
        </div>
      </div>

      <!-- Warning Alert -->
      <Alert
        variant="warning"
        class="mb-6"
        icon="fa-exclamation-triangle"
        :title="t('settings.team.resetPassword.warning.title', 'Important')"
        :description="
          t(
            'settings.team.resetPassword.warning.description',
            'User must change this password on next login. Save this password securely as it won\'t be shown again.',
          )
        "
      />

      <!-- Close Button -->
      <div class="flex justify-end">
        <Button
          variant="primary"
          :label="t('settings.team.resetPassword.close', 'Done')"
          @click="$emit('close')"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Button, Alert } from '@owlint/feathers-vue'
import { useI18n } from 'vue-i18n'

import type { TeamMember } from '@/types/team'

const { t } = useI18n()

const props = defineProps<{
  temporaryPassword: string
  member: TeamMember
}>()

// const emit = defineEmits<{ close: [] }>()

const memberDisplayName = computed(() => {
  if (props.member.first_name && props.member.last_name) {
    return `${props.member.first_name} ${props.member.last_name}`
  }
  return props.member.username
})

function copyPassword() {
  navigator.clipboard.writeText(props.temporaryPassword)
}
</script>
