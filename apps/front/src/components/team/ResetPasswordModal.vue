<template>
  <Modal
    :display-modal="true"
    :title="$t('settings.user.resetPassword.title')"
    icon="fa-key"
    size="md"
    color=""
    @close="emit('close')"
  >
    <!-- Success State -->
    <div v-if="isSuccess && newPassword" class="flex flex-col gap-6">
      <Alert
        variant="success"
        icon="fa-check-circle"
        :title="$t('settings.user.resetPassword.success')"
        :description="$t('settings.user.resetPassword.successDescription')"
      />

      <!-- New Password Display -->
      <div class="flex flex-col gap-2">
        <div class="flex gap-2">
          <Input
            id="reset-new-password"
            :model-value="newPassword"
            :type="showPassword ? 'text' : 'password'"
            :label="$t('settings.user.resetPassword.newPassword')"
            :icon-right="showPassword ? 'fa-eye-slash' : 'fa-eye'"
            readonly
            class="flex-1 font-mono"
            @click-icon-right="showPassword = !showPassword"
          />
          <Button variant="secondary" icon="fa-copy" class="self-end" @click="copyPassword" />
        </div>
        <p v-if="copied" class="text-success text-xs">
          <i class="fa fa-check mr-1"></i>
          {{ $t('common.copied') }}
        </p>
      </div>
    </div>

    <!-- Reset Form -->
    <div v-else class="flex flex-col gap-4">
      <p class="text-neutral-black-font text-sm">
        {{ $t('settings.user.resetPassword.description') }}
        <span class="font-semibold">{{ memberDisplayName }}</span>
      </p>

      <!-- Password Input -->
      <Input
        id="reset-password"
        v-model="password"
        :type="showPassword ? 'text' : 'password'"
        :disabled="isLoading"
        :label="`${$t('settings.user.resetPassword.temporaryPassword')} *`"
        :placeholder="$t('settings.user.resetPassword.placeholder')"
        :error="error || undefined"
        :icon-right="showPassword ? 'fa-eye-slash' : 'fa-eye'"
        class="w-full"
        @click-icon-right="showPassword = !showPassword"
      />

      <!-- Generate Password Button -->
      <div>
        <Button
          variant="tertiary"
          size="sm"
          icon="fa-refresh"
          :label="$t('settings.user.generatePassword')"
          :disabled="isLoading"
          @click="generatePassword"
        />
      </div>

      <Alert
        variant="info"
        icon="fa-info-circle"
        :title="$t('settings.user.resetPassword.infoTitle')"
        :description="$t('settings.user.resetPassword.infoDescription')"
      />
    </div>

    <template #footer>
      <template v-if="isSuccess && newPassword">
        <Button variant="primary" :label="$t('common.close')" @click="handleClose" />
      </template>
      <template v-else>
        <Button
          variant="primary"
          :label="$t('settings.user.resetPassword.button')"
          :loading="isLoading"
          :disabled="!password || isLoading"
          @click="handleResetPassword"
        />
        <Button
          variant="secondary"
          :label="$t('common.cancel')"
          :disabled="isLoading"
          @click="handleClose"
        />
      </template>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { useResetMemberPassword } from '@/mutations/team'
import type { TeamMemberListItem } from '@/types/team'
import { Alert, Button, Input, Modal } from '@owlint/feathers-vue'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const props = defineProps<{
  member: TeamMemberListItem
}>()

const emit = defineEmits<{
  close: []
}>()

// State
const password = ref('')
const newPassword = ref('')
const showPassword = ref(false)
const copied = ref(false)
const error = ref('')
const isSuccess = ref(false)

// Mutation
const { resetPasswordAsync, isPending: isLoading } = useResetMemberPassword()

const memberDisplayName = computed(() => {
  if (props.member.first_name && props.member.last_name) {
    return `${props.member.first_name} ${props.member.last_name}`
  }
  return props.member.username
})

function generatePassword() {
  const length = 12
  const charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*'
  let generatedPassword = ''

  generatedPassword += 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'[Math.floor(Math.random() * 26)]
  generatedPassword += 'abcdefghijklmnopqrstuvwxyz'[Math.floor(Math.random() * 26)]
  generatedPassword += '0123456789'[Math.floor(Math.random() * 10)]
  generatedPassword += '!@#$%^&*'[Math.floor(Math.random() * 8)]

  for (let i = generatedPassword.length; i < length; i++) {
    generatedPassword += charset[Math.floor(Math.random() * charset.length)]
  }

  password.value = generatedPassword
    .split('')
    .sort(() => 0.5 - Math.random())
    .join('')
}

function validatePassword(): boolean {
  if (!password.value) {
    error.value = t('common.validation.password.required')
    return false
  }

  if (password.value.length < 8) {
    error.value = t('common.validation.password.minLength')
    return false
  }

  if (!/[A-Z]/.test(password.value)) {
    error.value = t('common.validation.password.uppercase')
    return false
  }

  if (!/[a-z]/.test(password.value)) {
    error.value = t('common.validation.password.lowercase')
    return false
  }

  if (!/[0-9]/.test(password.value)) {
    error.value = t('common.validation.password.number')
    return false
  }

  if (!/[!@#$%^&*()_+\-=\[\]{}|;:,.<>?]/.test(password.value)) {
    error.value = t('common.validation.password.specialChar')
    return false
  }

  error.value = ''
  return true
}

async function handleResetPassword() {
  if (!validatePassword()) return

  try {
    await resetPasswordAsync({
      userId: props.member.id,
      temporaryPassword: password.value,
    })

    newPassword.value = password.value
    isSuccess.value = true
  } catch (err: unknown) {
    error.value = err instanceof Error ? err.message : 'Failed to reset password'
  }
}

async function copyPassword() {
  try {
    await navigator.clipboard.writeText(newPassword.value)
    copied.value = true
    setTimeout(() => {
      copied.value = false
    }, 2000)
  } catch {
    // Fallback for older browsers
    const textArea = document.createElement('textarea')
    textArea.value = newPassword.value
    document.body.appendChild(textArea)
    textArea.select()
    document.execCommand('copy')
    document.body.removeChild(textArea)
    copied.value = true
    setTimeout(() => {
      copied.value = false
    }, 2000)
  }
}

function handleClose() {
  emit('close')
}
</script>
