<template>
  <Modal
    :display-modal="true"
    :title="$t('settings.user.create.title')"
    icon="fa-user-plus"
    size="md"
    color=""
    @close="emit('cancel')"
  >
    <template #description>
      <div class="flex flex-col gap-4">
        <p class="text-neutral-black-font">
          {{ $t('settings.user.create.description') }}
        </p>

        <form class="flex flex-col gap-4" @submit.prevent="handleSubmit">
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
              :type="showPassword ? 'text' : 'password'"
              required
              :disabled="isLoading"
              :label="`${$t('settings.user.temporaryPassword')} *`"
              :placeholder="$t('settings.user.passwordPlaceholder')"
              :error="
                touched.temporaryPassword && errors.temporaryPassword
                  ? errors.temporaryPassword
                  : undefined
              "
              :icon-right="showPassword ? 'fa-eye-slash' : 'fa-eye'"
              class="w-full"
              @blur="touchField('temporaryPassword')"
              @input="validateForm()"
              @click-icon-right="showPassword = !showPassword"
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
              icon="fa-refresh"
              :label="$t('settings.user.generatePassword')"
              :disabled="isLoading"
              @click="generatePassword"
            />
          </div>

          <!-- Initial Role Selection -->
          <div class="border-primary-lighter-stroke flex flex-col gap-2 border-t pt-4">
            <label class="text-neutral-black-font block text-sm font-medium">
              {{ $t('settings.user.initialRole') }}
            </label>
            <p class="text-neutral-black-font text-xs">
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
                  v-model="selectedRoleId"
                  :value="role.id"
                  :disabled="isLoading"
                  name="role"
                />
                <div class="flex flex-1 items-center gap-2">
                  <div class="bg-primary/10 flex h-8 w-8 items-center justify-center rounded-sm">
                    <i :class="['fa', role.icon, 'text-primary text-sm']"></i>
                  </div>
                  <div>
                    <p class="text-sm font-medium">{{ role.name }}</p>
                    <p class="text-neutral-black-font text-xs">{{ role.description }}</p>
                  </div>
                </div>
              </label>
            </div>
          </div>
        </form>
      </div>
    </template>

    <template #footer>
      <Button
        variant="primary"
        icon="fa-user-plus"
        :label="$t('settings.user.create.button')"
        :loading="isLoading"
        :disabled="isLoading || !isFormValid"
        @click="handleSubmit"
      />
      <Button
        variant="tertiary"
        :label="$t('common.cancel')"
        :disabled="isLoading"
        @click="emit('cancel')"
      />
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { useRoles } from '@/composables/useRoles'
import type { OrganizationUserCreate } from '@/types/user'
import { isValidEmail } from '@/utils/validators'
import { Button, Input, Modal, Radio } from '@owlint/feathers-vue'
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

const showPassword = ref(false)
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

const isFormValid = computed(() => {
  return (
    form.username.trim().length >= 3 &&
    isValidEmail(form.email.trim()) &&
    form.temporaryPassword.trim().length >= 8
  )
})

function generatePassword() {
  const length = 12
  const charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*'
  let password = ''

  password += 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'[Math.floor(Math.random() * 26)]
  password += 'abcdefghijklmnopqrstuvwxyz'[Math.floor(Math.random() * 26)]
  password += '0123456789'[Math.floor(Math.random() * 10)]
  password += '!@#$%^&*'[Math.floor(Math.random() * 8)]

  for (let i = password.length; i < length; i++) {
    password += charset[Math.floor(Math.random() * charset.length)]
  }

  form.temporaryPassword = password
    .split('')
    .sort(() => 0.5 - Math.random())
    .join('')
}

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

function handleSubmit() {
  // Touch all fields so errors become visible when user clicks submit
  touched.username = true
  touched.email = true
  touched.temporaryPassword = true

  if (!validateForm()) return

  const permissions = getPermissionsForRole(selectedRoleId.value as 'reader' | 'writer' | 'admin')

  emit('confirm', {
    ...form,
    permissions,
  })
}
</script>
