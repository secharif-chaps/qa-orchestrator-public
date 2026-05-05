<template>
  <div
    class="fixed inset-0 z-50 flex items-center justify-center bg-white/20 backdrop-blur-sm"
    @click.self="handleClose"
  >
    <div
      class="border-primary-lighter-stroke mx-4 w-full max-w-112 rounded-xl border bg-white p-6 shadow-2xl"
    >
      <!-- Header -->
      <div class="mb-6 flex items-center justify-between">
        <h3 class="text-base text-lg font-semibold">
          {{ $t('settings.user.resetPassword.title') }}
        </h3>
        <Button variant="tertiary" icon="fa fa-times" @click="handleClose" />
      </div>

      <!-- Success State -->
      <template v-if="isSuccess && newPassword">
        <Alert
          variant="success"
          class="mb-6"
          icon="fa-check-circle"
          :title="$t('settings.user.resetPassword.success')"
          :description="$t('settings.user.resetPassword.successDescription')"
        />

        <!-- New Password Display -->
        <div class="mb-6">
          <div class="flex gap-2">
            <Input
              id="reset-new-password"
              :model-value="newPassword"
              :type="showPassword ? 'text' : 'password'"
              :label="$t('settings.user.resetPassword.newPassword')"
              :icon-right="showPassword ? 'fa fa-eye-slash' : 'fa fa-eye'"
              readonly
              class="flex-1 font-mono"
              @click-icon-right="showPassword = !showPassword"
            />
            <Button variant="secondary" icon="fa fa-copy" class="self-end" @click="copyPassword" />
          </div>
          <p v-if="copied" class="text-success mt-1 text-xs">
            <i class="fa fa-check mr-1"></i>
            {{ $t('common.copied') }}
          </p>
        </div>

        <!-- Close Button -->
        <div class="flex justify-end">
          <Button variant="primary" :label="$t('common.close')" @click="handleClose" />
        </div>
      </template>

      <!-- Reset Form -->
      <template v-else>
        <!-- User Info -->
        <div class="mb-6">
          <p class="text-neutral-black-font text-sm">
            {{ $t('settings.user.resetPassword.description') }}
            <span class="font-semibold">{{ username }}</span>
          </p>
        </div>

        <!-- Password Input -->
        <div class="mb-4">
          <Input
            id="reset-password"
            v-model="password"
            :type="showPassword ? 'text' : 'password'"
            :disabled="isLoading"
            :label="`${$t('settings.user.resetPassword.temporaryPassword')} *`"
            :placeholder="$t('settings.user.resetPassword.placeholder')"
            :error="error || undefined"
            :icon-right="showPassword ? 'fa fa-eye-slash' : 'fa fa-eye'"
            class="w-full"
            @click-icon-right="showPassword = !showPassword"
          />
        </div>

        <!-- Generate Password Button -->
        <div class="mb-6">
          <button
            type="button"
            :disabled="isLoading"
            class="text-neutral-black-font hover:text-primary text-sm font-medium disabled:opacity-50"
            @click="generatePassword"
          >
            <i class="fa fa-refresh mr-1"></i>
            {{ $t('settings.user.generatePassword') }}
          </button>
        </div>

        <!-- Info Alert -->
        <Alert
          variant="info"
          class="mb-6"
          icon="fa-info-circle"
          :title="$t('settings.user.resetPassword.infoTitle')"
          :description="$t('settings.user.resetPassword.infoDescription')"
        />

        <!-- Actions -->
        <div class="flex justify-end gap-3">
          <Button
            variant="secondary"
            :label="$t('common.cancel')"
            :disabled="isLoading"
            @click="handleClose"
          />
          <Button
            variant="primary"
            :label="$t('settings.user.resetPassword.button')"
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
import { useResetUserPassword } from '@/mutations/admin-users'
import { Alert, Button, Input } from '@owlint/feathers-vue'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const props = defineProps<{
  userId: string
  username: string
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
const { resetPasswordAsync, isLoading } = useResetUserPassword()

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
      userId: props.userId,
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
