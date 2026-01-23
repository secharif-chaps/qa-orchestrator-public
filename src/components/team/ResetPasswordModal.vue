<template>
  <div
    class="fixed inset-0 bg-base-100/20 backdrop-blur-sm flex items-center justify-center z-50"
    @click.self="handleClose"
  >
    <div
      class="bg-base-100 rounded-xl shadow-2xl border border-primary-stroke p-6 max-w-md w-full mx-4"
    >
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <h3 class="text-lg font-semibold text-base">
          {{ $t('user.resetPassword.title', 'Reset Password') }}
        </h3>
        <Button variant="tertiary" icon="fa fa-times" @click="handleClose" />
      </div>

      <!-- Success State -->
      <template v-if="isSuccess && newPassword">
        <Alert
          variant="success"
          class="mb-6"
          icon="fa-check-circle"
          :title="$t('user.resetPassword.success', 'Password reset successfully!')"
          :description="$t('user.resetPassword.successDescription', 'Share the new password with the user. They will be required to change it on first login.')"
        />

        <!-- New Password Display -->
        <div class="mb-6">
          <label class="block text-sm font-medium text-secondary mb-2">
            {{ $t('user.resetPassword.newPassword', 'New Temporary Password') }}
          </label>
          <div class="flex gap-2">
            <div class="flex-1 relative">
              <input
                :type="showPassword ? 'text' : 'password'"
                :value="newPassword"
                readonly
                class="w-full px-3 py-2 pr-10 border border-primary-stroke rounded-lg bg-base-200 font-mono text-sm"
              />
              <button
                type="button"
                class="absolute right-3 top-1/2 transform -translate-y-1/2 text-secondary hover:text-base"
                @click="showPassword = !showPassword"
              >
                <i :class="showPassword ? 'fa fa-eye-slash' : 'fa fa-eye'"></i>
              </button>
            </div>
            <Button
              variant="secondary"
              icon="fa fa-copy"
              @click="copyPassword"
            />
          </div>
          <p v-if="copied" class="text-xs text-success mt-1">
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
          <p class="text-sm text-secondary">
            {{ $t('user.resetPassword.description', 'Reset password for') }}
            <span class="font-semibold">{{ memberDisplayName }}</span>
          </p>
        </div>

        <!-- Password Input -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-secondary mb-2">
            {{ $t('user.resetPassword.temporaryPassword', 'Temporary Password') }} *
          </label>
          <div class="relative">
            <input
              v-model="password"
              :type="showPassword ? 'text' : 'password'"
              :disabled="isLoading"
              class="w-full px-3 py-2 pr-10 border border-primary-stroke rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent disabled:opacity-50 disabled:cursor-not-allowed"
              :placeholder="$t('user.resetPassword.placeholder', 'Enter temporary password')"
            />
            <button
              type="button"
              class="absolute right-3 top-1/2 transform -translate-y-1/2 text-secondary hover:text-base"
              :disabled="isLoading"
              @click="showPassword = !showPassword"
            >
              <i :class="showPassword ? 'fa fa-eye-slash' : 'fa fa-eye'"></i>
            </button>
          </div>
          <p v-if="error" class="text-error text-xs mt-1">{{ error }}</p>
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
            {{ $t('user.generatePassword', 'Generate Random Password') }}
          </button>
        </div>

        <!-- Info Alert -->
        <Alert
          variant="info"
          class="mb-6"
          icon="fa-info-circle"
          :title="$t('user.resetPassword.infoTitle', 'Password will be temporary')"
          :description="$t('user.resetPassword.infoDescription', 'The user will be required to change this password on their next login.')"
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
            :label="$t('user.resetPassword.button', 'Reset Password')"
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
    error.value = 'Password is required'
    return false
  }

  if (password.value.length < 8) {
    error.value = 'Password must be at least 8 characters'
    return false
  }

  if (!/[A-Z]/.test(password.value)) {
    error.value = 'Password must contain at least one uppercase letter'
    return false
  }

  if (!/[a-z]/.test(password.value)) {
    error.value = 'Password must contain at least one lowercase letter'
    return false
  }

  if (!/[0-9]/.test(password.value)) {
    error.value = 'Password must contain at least one number'
    return false
  }

  if (!/[!@#$%^&*()_+\-=\[\]{}|;:,.<>?]/.test(password.value)) {
    error.value = 'Password must contain at least one special character'
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
  } catch (err: any) {
    error.value = err?.message || 'Failed to reset password'
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
