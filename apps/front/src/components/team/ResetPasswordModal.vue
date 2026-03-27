<template>
  <div
    class="bg-base-100/20 fixed inset-0 z-50 flex items-center justify-center backdrop-blur-sm"
    @click.self="handleClose"
  >
    <div
      class="bg-base-100 border-primary-stroke mx-4 w-full max-w-md rounded-xl border p-6 shadow-2xl"
    >
      <!-- Header -->
      <div class="mb-6 flex items-center justify-between">
        <h3 class="text-base text-lg font-semibold">
          {{ $t('settings.user.resetPassword.title', 'Reset Password') }}
        </h3>
        <Button variant="tertiary" icon="fa fa-times" @click="handleClose" />
      </div>

      <!-- Success State -->
      <template v-if="isSuccess && newPassword">
        <Alert
          variant="success"
          class="mb-6"
          icon="fa-check-circle"
          :title="$t('settings.user.resetPassword.success', 'Password reset successfully!')"
          :description="
            $t(
              'settings.user.resetPassword.successDescription',
              'Share the new password with the user. They will be required to change it on first login.',
            )
          "
        />

        <!-- New Password Display -->
        <div class="mb-6">
          <label class="text-secondary mb-2 block text-sm font-medium">
            {{ $t('settings.user.resetPassword.newPassword', 'New Temporary Password') }}
          </label>
          <div class="flex gap-2">
            <div class="relative flex-1">
              <input
                :type="showPassword ? 'text' : 'password'"
                :value="newPassword"
                readonly
                class="border-primary-stroke bg-base-200 w-full rounded-lg border px-3 py-2 pr-10 font-mono text-sm"
              />
              <button
                type="button"
                class="text-secondary absolute top-1/2 right-3 -translate-y-1/2 transform hover:text-base"
                @click="showPassword = !showPassword"
              >
                <i :class="showPassword ? 'fa fa-eye-slash' : 'fa fa-eye'"></i>
              </button>
            </div>
            <Button variant="secondary" icon="fa fa-copy" @click="copyPassword" />
          </div>
          <p v-if="copied" class="text-success mt-1 text-xs">
            <i class="fa fa-check mr-1"></i>
            {{ $t('common.copied', 'Copied to clipboard!') }}
          </p>
        </div>

        <!-- Close Button -->
        <div class="flex justify-end">
          <Button variant="primary" :label="$t('common.close', 'Close')" @click="handleClose" />
        </div>
      </template>

      <!-- Reset Form -->
      <template v-else>
        <!-- User Info -->
        <div class="mb-6">
          <p class="text-secondary text-sm">
            {{ $t('settings.user.resetPassword.description', 'Reset password for') }}
            <span class="font-semibold">{{ memberDisplayName }}</span>
          </p>
        </div>

        <!-- Password Input -->
        <div class="mb-4">
          <label class="text-secondary mb-2 block text-sm font-medium">
            {{ $t('settings.user.resetPassword.temporaryPassword', 'Temporary Password') }} *
          </label>
          <div class="relative">
            <input
              v-model="password"
              :type="showPassword ? 'text' : 'password'"
              :disabled="isLoading"
              class="border-primary-stroke focus:ring-primary w-full rounded-lg border px-3 py-2 pr-10 focus:border-transparent focus:ring-2 disabled:cursor-not-allowed disabled:opacity-50"
              :placeholder="
                $t('settings.user.resetPassword.placeholder', 'Enter temporary password')
              "
            />
            <button
              type="button"
              class="text-secondary absolute top-1/2 right-3 -translate-y-1/2 transform hover:text-base"
              :disabled="isLoading"
              @click="showPassword = !showPassword"
            >
              <i :class="showPassword ? 'fa fa-eye-slash' : 'fa fa-eye'"></i>
            </button>
          </div>
          <p v-if="error" class="text-error mt-1 text-xs">{{ error }}</p>
        </div>

        <!-- Generate Password Button -->
        <div class="mb-6">
          <button
            type="button"
            :disabled="isLoading"
            class="text-secondary hover:text-primary text-sm font-medium disabled:opacity-50"
            @click="generatePassword"
          >
            <i class="fa fa-refresh mr-1"></i>
            {{ $t('settings.user.generatePassword', 'Generate Random Password') }}
          </button>
        </div>

        <!-- Info Alert -->
        <Alert
          variant="info"
          class="mb-6"
          icon="fa-info-circle"
          :title="$t('settings.user.resetPassword.infoTitle', 'Password will be temporary')"
          :description="
            $t(
              'settings.user.resetPassword.infoDescription',
              'The user will be required to change this password on their next login.',
            )
          "
        />

        <!-- Actions -->
        <div class="flex justify-end gap-3">
          <Button
            variant="secondary"
            :label="$t('common.cancel', 'Cancel')"
            :disabled="isLoading"
            @click="handleClose"
          />
          <Button
            variant="primary"
            :label="$t('settings.user.resetPassword.button', 'Reset Password')"
            :loading="isLoading"
            :disabled="!password || isLoading"
            @click="handleResetPassword"
          />
        </div>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import type { TeamMemberListItem } from '@/types/team'
import { Alert, Button } from '@owlint/feathers-vue'
import { useResetMemberPassword } from '@/mutations/team'
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

// Computed
const memberDisplayName = computed(() => {
  if (props.member.first_name && props.member.last_name) {
    return `${props.member.first_name} ${props.member.last_name}`
  }
  return props.member.username
})

// Generate a random password that meets requirements
function generatePassword() {
  const length = 12
  const charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*'
  let generatedPassword = ''

  // Ensure at least one of each type
  generatedPassword += 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'[Math.floor(Math.random() * 26)]
  generatedPassword += 'abcdefghijklmnopqrstuvwxyz'[Math.floor(Math.random() * 26)]
  generatedPassword += '0123456789'[Math.floor(Math.random() * 10)]
  generatedPassword += '!@#$%^&*'[Math.floor(Math.random() * 8)]

  // Fill the rest randomly
  for (let i = generatedPassword.length; i < length; i++) {
    generatedPassword += charset[Math.floor(Math.random() * charset.length)]
  }

  // Shuffle the password
  password.value = generatedPassword
    .split('')
    .sort(() => 0.5 - Math.random())
    .join('')
}

// Validate password
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

// Handle reset password
async function handleResetPassword() {
  if (!validatePassword()) return

  try {
    await resetPasswordAsync({
      userId: props.member.id,
      temporaryPassword: password.value,
    })

    // Store the password for display and show success state
    newPassword.value = password.value
    isSuccess.value = true
  } catch (err: unknown) {
    error.value = err instanceof Error ? err.message : 'Failed to reset password'
  }
}

// Copy password to clipboard
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

// Handle close
function handleClose() {
  emit('close')
}
</script>
