<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-white/20 backdrop-blur-sm">
    <div class="w-full max-w-112 rounded-sm bg-white shadow-xl">
      <!-- Header -->
      <div class="border-primary-lighter-stroke border-b px-6 py-4">
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-semibold">
            {{ $t('settings.user.create.title') }}
          </h2>
          <button
            @click="$emit('cancel')"
            class="text-neutral-black-font p-1 transition-colors hover:text-base"
          >
            <i class="fa fa-times"></i>
          </button>
        </div>
        <p class="text-neutral-black-font mt-1 text-sm">
          {{ $t('settings.user.create.description') }}
        </p>
      </div>

      <!-- Form -->
      <form @submit.prevent="handleSubmit" class="space-y-4 px-6 py-4">
        <!-- Username -->
        <Input
          id="create-username"
          v-model="form.username"
          type="text"
          required
          :disabled="isLoading"
          :label="`${$t('settings.user.username')} *`"
          :placeholder="$t('settings.user.usernamePlaceholder')"
          :error="touched.username && errors.username ? errors.username : undefined"
          class="w-full"
          @blur="touchField('username')"
          @input="validateForm()"
        />

        <!-- Email -->
        <Input
          id="create-email"
          v-model="form.email"
          type="email"
          required
          :disabled="isLoading"
          :label="`${$t('settings.user.email')} *`"
          :placeholder="$t('settings.user.emailPlaceholder')"
          :error="touched.email && errors.email ? errors.email : undefined"
          class="w-full"
          @blur="touchField('email')"
          @input="validateForm()"
        />

        <!-- Temporary Password -->
        <div>
          <Input
            id="create-password"
            v-model="form.temporaryPassword"
            type="password"
            required
            :disabled="isLoading"
            :label="`${$t('settings.user.temporaryPassword')} *`"
            :placeholder="$t('settings.user.passwordPlaceholder')"
            :error="
              touched.temporaryPassword && errors.temporaryPassword
                ? errors.temporaryPassword
                : undefined
            "
            class="w-full"
            @blur="touchField('temporaryPassword')"
            @input="validateForm()"
          />
          <p
            v-if="!(touched.temporaryPassword && errors.temporaryPassword)"
            class="text-neutral-black-font mt-1 text-xs"
          >
            {{ $t('settings.user.passwordHelp') }}
          </p>
        </div>

        <!-- Auto-generate password button -->
        <div>
          <Button
            variant="tertiary"
            size="sm"
            icon="fa-rotate-right"
            :label="$t('settings.user.generatePassword')"
            :disabled="isLoading"
            @click="generatePassword"
          />
        </div>

        <!-- Initial Role Selection -->
        <div class="border-primary-lighter-stroke border-t pt-4">
          <label class="text-neutral-black-font mb-2 block text-sm font-medium">
            {{ $t('settings.user.initialRole') }}
          </label>
          <p class="text-neutral-black-font mb-3 text-xs">
            {{ $t('settings.user.initialRoleDescription') }}
          </p>
          <div class="flex flex-col gap-2">
            <label
              v-for="role in availableRoles"
              :key="role.id"
              class="flex cursor-pointer items-center gap-3 rounded-sm border p-3 transition-colors"
              :class="
                selectedRoleId === role.id
                  ? 'border-primary bg-primary/5'
                  : 'border-primary-lighter-stroke hover:border-primary/30'
              "
            >
              <Radio
                :id="`role-${role.id}`"
                :value="role.id"
                v-model="selectedRoleId"
                :disabled="isLoading"
                name="role"
              />
              <div class="flex flex-1 items-center gap-2">
                <div class="bg-primary/10 flex h-8 w-8 items-center justify-center rounded-sm">
                  <i :class="['fa', role.icon, 'text-primary text-sm']"></i>
                </div>
                <div>
                  <p class="text-sm font-medium">{{ role.name }}</p>
                  <p class="text-neutral-black-font text-xs">
                    {{ role.description }}
                  </p>
                </div>
              </div>
            </label>
          </div>
        </div>
      </form>

      <!-- Actions -->
      <div class="border-primary-lighter-stroke flex justify-end gap-3 border-t px-6 py-4">
        <button
          type="button"
          @click="$emit('cancel')"
          :disabled="isLoading"
          class="text-neutral-black-font px-4 py-2 transition-colors hover:text-base disabled:opacity-50"
        >
          {{ $t('common.cancel') }}
        </button>
        <button
          @click="handleSubmit"
          :disabled="isLoading"
          class="flex items-center gap-2 rounded-sm px-6 py-2 text-white transition-colors disabled:cursor-not-allowed disabled:opacity-50"
          :class="
            isFormValid
              ? 'bg-primary hover:bg-primary/80 cursor-pointer'
              : 'bg-primary/50 cursor-not-allowed'
          "
        >
          <div
            v-if="isLoading"
            class="h-4 w-4 animate-spin rounded-full border-b-2 border-white"
          ></div>
          <i v-else class="fa fa-user-plus"></i>
          {{ $t('settings.user.create.button') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useRoles } from '@/composables/useRoles'
import type { OrganizationUserCreate } from '@/types/user'
import { Button, Input, Radio } from '@owlint/feathers-vue'
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

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

const errors = reactive<Record<string, string>>({})
const touched = reactive({
  username: false,
  email: false,
  temporaryPassword: false,
})

function touchField(field: keyof typeof touched) {
  touched[field] = true
  validateForm()
}

// Form validation
const isFormValid = computed(() => {
  return (
    form.username.trim().length >= 3 &&
    form.email.trim() !== '' &&
    form.temporaryPassword.trim().length >= 8 &&
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
  errors.username = ''
  errors.email = ''
  errors.temporaryPassword = ''

  if (!form.username.trim()) {
    errors.username = t('settings.user.validation.username.required')
  } else if (form.username.length < 3) {
    errors.username = t('settings.user.validation.username.minLength')
  }

  if (!form.email.trim()) {
    errors.email = t('settings.user.validation.email.required')
  } else if (!isValidEmail(form.email)) {
    errors.email = t('settings.user.validation.email.invalid')
  }

  if (!form.temporaryPassword.trim()) {
    errors.temporaryPassword = t('settings.user.validation.temporaryPassword.required')
  } else if (form.temporaryPassword.length < 8) {
    errors.temporaryPassword = t('settings.user.validation.temporaryPassword.minLength')
  }

  return !errors.username && !errors.email && !errors.temporaryPassword
}

// Handle form submission
function handleSubmit() {
  // Touch all fields so errors become visible when user clicks submit
  touched.username = true
  touched.email = true
  touched.temporaryPassword = true

  if (!validateForm()) return

  // Get permissions for selected role
  const permissions = getPermissionsForRole(selectedRoleId.value as 'reader' | 'writer' | 'admin')

  emit('confirm', {
    ...form,
    permissions, // Include permissions with the user creation
  })
}
</script>
