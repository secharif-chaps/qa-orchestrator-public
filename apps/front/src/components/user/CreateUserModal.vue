<template>
  <div class="bg-base-100/20 fixed inset-0 z-50 flex items-center justify-center backdrop-blur-sm">
    <div class="bg-base-100 w-full max-w-md rounded-lg shadow-xl">
      <!-- Header -->
      <div class="border-primary-stroke border-b px-6 py-4">
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-semibold">
            {{ $t('user.create.title', 'Create New User') }}
          </h2>
          <button
            @click="$emit('cancel')"
            class="text-secondary p-1 transition-colors hover:text-base"
          >
            <i class="fa fa-times"></i>
          </button>
        </div>
        <p class="text-secondary mt-1 text-sm">
          {{
            $t('user.create.description', 'User will be prompted to reset password on first login')
          }}
        </p>
      </div>

      <!-- Form -->
      <form @submit.prevent="handleSubmit" class="space-y-4 px-6 py-4">
        <!-- Username -->
        <div>
          <label class="text-secondary mb-1 block text-sm font-medium">
            {{ $t('user.username', 'Username') }} *
          </label>
          <input
            v-model="form.username"
            type="text"
            required
            :disabled="isLoading"
            class="border-primary-stroke focus:ring-primary w-full rounded-lg border px-3 py-2 focus:border-transparent focus:ring-2 disabled:cursor-not-allowed disabled:opacity-50"
            :placeholder="$t('user.usernamePlaceholder', 'Enter username')"
          />
          <p v-if="errors.username" class="mt-1 text-xs text-red-600">
            {{ errors.username }}
          </p>
        </div>

        <!-- Email -->
        <div>
          <label class="text-secondary mb-1 block text-sm font-medium">
            {{ $t('user.email', 'Email') }} *
          </label>
          <input
            v-model="form.email"
            type="email"
            required
            :disabled="isLoading"
            class="border-primary-stroke focus:ring-primary w-full rounded-lg border px-3 py-2 focus:border-transparent focus:ring-2 disabled:cursor-not-allowed disabled:opacity-50"
            :placeholder="$t('user.emailPlaceholder', 'Enter email address')"
          />
          <p v-if="errors.email" class="mt-1 text-xs text-red-600">
            {{ errors.email }}
          </p>
        </div>

        <!-- Temporary Password -->
        <div>
          <label class="text-secondary mb-1 block text-sm font-medium">
            {{ $t('user.temporaryPassword', 'Temporary Password') }} *
          </label>
          <div class="relative">
            <input
              v-model="form.temporaryPassword"
              :type="showPassword ? 'text' : 'password'"
              required
              :disabled="isLoading"
              class="border-primary-stroke focus:ring-primary w-full rounded-lg border px-3 py-2 pr-10 focus:border-transparent focus:ring-2 disabled:cursor-not-allowed disabled:opacity-50"
              :placeholder="$t('user.passwordPlaceholder', 'Enter temporary password')"
            />
            <button
              type="button"
              @click="showPassword = !showPassword"
              class="text-secondary absolute top-1/2 right-3 -translate-y-1/2 transform hover:text-base"
              :disabled="isLoading"
            >
              <i :class="showPassword ? 'fa fa-eye-slash' : 'fa fa-eye'"></i>
            </button>
          </div>
          <p class="text-secondary mt-1 text-xs">
            {{
              $t(
                'user.passwordHelp',
                'User will be required to change this password on first login',
              )
            }}
          </p>
          <p v-if="errors.temporaryPassword" class="mt-1 text-xs text-red-600">
            {{ errors.temporaryPassword }}
          </p>
        </div>

        <!-- Auto-generate password button -->
        <div>
          <button
            type="button"
            @click="generatePassword"
            :disabled="isLoading"
            class="text-secondary hover:text-sage-content/80 text-sm font-medium disabled:opacity-50"
          >
            <i class="fa fa-refresh mr-1"></i>
            {{ $t('user.generatePassword', 'Generate Random Password') }}
          </button>
        </div>

        <!-- Initial Role Selection -->
        <div class="border-primary-stroke border-t pt-4">
          <label class="text-secondary mb-2 block text-sm font-medium">
            {{ $t('user.initialRole', 'Initial Role') }}
          </label>
          <p class="text-secondary mb-3 text-xs">
            {{
              $t(
                'user.initialRoleDescription',
                'Select the initial permissions for this user. Can be changed later.',
              )
            }}
          </p>
          <div class="flex flex-col gap-2">
            <label
              v-for="role in availableRoles"
              :key="role.id"
              class="flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-colors"
              :class="
                selectedRoleId === role.id
                  ? 'border-primary bg-primary/5'
                  : 'border-primary-stroke hover:border-primary/30'
              "
            >
              <input
                type="radio"
                :value="role.id"
                v-model="selectedRoleId"
                :disabled="isLoading"
                class="text-primary focus:ring-primary border-primary-stroke h-4 w-4"
              />
              <div class="flex flex-1 items-center gap-2">
                <div class="bg-primary/10 flex h-8 w-8 items-center justify-center rounded-lg">
                  <i :class="['fa', role.icon, 'text-primary text-sm']"></i>
                </div>
                <div>
                  <p class="text-sm font-medium">{{ role.name }}</p>
                  <p class="text-secondary text-xs">{{ role.description }}</p>
                </div>
              </div>
            </label>
          </div>
        </div>
      </form>

      <!-- Actions -->
      <div class="border-primary-stroke flex justify-end gap-3 border-t px-6 py-4">
        <button
          type="button"
          @click="$emit('cancel')"
          :disabled="isLoading"
          class="text-secondary px-4 py-2 transition-colors hover:text-base disabled:opacity-50"
        >
          {{ $t('common.cancel', 'Cancel') }}
        </button>
        <button
          @click="handleSubmit"
          :disabled="isLoading || !isFormValid"
          class="bg-primary hover:bg-primary/80 flex items-center gap-2 rounded-lg px-6 py-2 text-white transition-colors disabled:cursor-not-allowed disabled:opacity-50"
        >
          <div
            v-if="isLoading"
            class="h-4 w-4 animate-spin rounded-full border-b-2 border-white"
          ></div>
          <i v-else class="fa fa-user-plus"></i>
          {{ $t('user.create.button', 'Add User') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoles } from '@/composables/useRoles'
import type { OrganizationUserCreate } from '@/types/user'

const { t } = useI18n()
const { getAllRoles, getPermissionsForRole } = useRoles()

interface Props {
  isLoading?: boolean
}

interface Emits {
  confirm: [user: OrganizationUserCreate]
  cancel: []
}

defineProps<Props>()
const emit = defineEmits<Emits>()

// Get available roles (exclude admin from initial creation)
const availableRoles = computed(() => {
  return getAllRoles().filter((role) => role.id !== 'admin')
})

// Default to 'reader' role (organization.read only)
const selectedRoleId = ref<string>('reader')

// Form state
const form = reactive<OrganizationUserCreate>({
  username: '',
  email: '',
  firstName: '',
  lastName: '',
  temporaryPassword: '',
})

const showPassword = ref(false)
const errors = ref<Record<string, string>>({})

// Form validation
const isFormValid = computed(() => {
  return (
    form.username.trim() !== '' &&
    form.email.trim() !== '' &&
    form.temporaryPassword.trim() !== '' &&
    isValidEmail(form.email) &&
    selectedRoleId.value !== ''
  )
})

function isValidEmail(email: string) {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  return emailRegex.test(email)
}

// Generate random password
function generatePassword() {
  const length = 12
  const charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*'
  let password = ''

  // Ensure at least one of each type
  password += 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'[Math.floor(Math.random() * 26)]
  password += 'abcdefghijklmnopqrstuvwxyz'[Math.floor(Math.random() * 26)]
  password += '0123456789'[Math.floor(Math.random() * 10)]
  password += '!@#$%^&*'[Math.floor(Math.random() * 8)]

  // Fill the rest randomly
  for (let i = password.length; i < length; i++) {
    password += charset[Math.floor(Math.random() * charset.length)]
  }

  // Shuffle the password
  form.temporaryPassword = password
    .split('')
    .sort(() => 0.5 - Math.random())
    .join('')
}

// Form validation
function validateForm() {
  errors.value = {}

  if (!form.username.trim()) {
    errors.value.username = t('user.validation.username.required', 'Username is required')
  } else if (form.username.length < 3) {
    errors.value.username = t(
      'user.validation.username.minLength',
      'Username must be at least 3 characters',
    )
  }

  if (!form.email.trim()) {
    errors.value.email = t('user.validation.email.required', 'Email is required')
  } else if (!isValidEmail(form.email)) {
    errors.value.email = t('user.validation.email.invalid', 'Please enter a valid email address')
  }

  if (!form.temporaryPassword.trim()) {
    errors.value.temporaryPassword = t(
      'user.validation.temporaryPassword.required',
      'Temporary password is required',
    )
  } else if (form.temporaryPassword.length < 8) {
    errors.value.temporaryPassword = t(
      'user.validation.temporaryPassword.minLength',
      'Password must be at least 8 characters',
    )
  }

  return Object.keys(errors.value).length === 0
}

// Handle form submission
function handleSubmit() {
  if (!validateForm()) return

  // Get permissions for selected role
  const permissions = getPermissionsForRole(selectedRoleId.value as 'reader' | 'writer' | 'admin')

  emit('confirm', {
    ...form,
    permissions, // Include permissions with the user creation
  })
}
</script>
