<template>
  <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-bg1 rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
      <div class="px-6 py-4 border-b border-border-2">
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-semibold">
            {{
              isEditing
                ? $t('team.edit.title', 'Edit User')
                : $t('team.create.title', 'Create User')
            }}
          </h2>
          <button
            @click="$emit('cancel')"
            class="text-secondary hover:text-base transition-colors p-1"
          >
            <i class="fa fa-times"></i>
          </button>
        </div>
        <p class="text-secondary text-sm mt-1">
          {{
            isEditing
              ? $t('team.edit.description', 'Update user information and permissions')
              : $t('team.create.description', 'Add a new user to your workspace')
          }}
        </p>
      </div>

      <form @submit.prevent="handleSubmit" class="px-6 py-4 space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-secondary mb-1">
              {{ $t('team.firstName', 'First Name') }}
              <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.firstName"
              type="text"
              required
              :disabled="isLoading"
              class="w-full px-3 py-2 border border-border-2 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent disabled:opacity-50 disabled:cursor-not-allowed"
              :placeholder="$t('team.firstNamePlaceholder', 'Enter first name')"
            />
            <p v-if="errors.firstName" class="text-red-600 text-xs mt-1">
              {{ errors.firstName }}
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium text-secondary mb-1">
              {{ $t('team.lastName', 'Last Name') }}
              <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.lastName"
              type="text"
              required
              :disabled="isLoading"
              class="w-full px-3 py-2 border border-border-2 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent disabled:opacity-50 disabled:cursor-not-allowed"
              :placeholder="$t('team.lastNamePlaceholder', 'Enter last name')"
            />
            <p v-if="errors.lastName" class="text-red-600 text-xs mt-1">
              {{ errors.lastName }}
            </p>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-secondary mb-1">
            {{ $t('team.username', 'Username') }}
            <span class="text-red-500">*</span>
          </label>
          <input
            v-model="form.username"
            type="text"
            required
            :disabled="isLoading || isEditing"
            class="w-full px-3 py-2 border border-border-2 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent disabled:opacity-50 disabled:cursor-not-allowed"
            :placeholder="$t('team.usernamePlaceholder', 'Enter username')"
          />
          <p v-if="errors.username" class="text-red-600 text-xs mt-1">
            {{ errors.username }}
          </p>
          <p v-if="isEditing" class="text-xs text-secondary mt-1">
            {{ $t('team.usernameCannotChange', 'Username cannot be changed') }}
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium text-secondary mb-1">
            {{ $t('team.email', 'Email') }}
            <span class="text-red-500">*</span>
          </label>
          <input
            v-model="form.email"
            type="email"
            required
            :disabled="isLoading || isEditing"
            class="w-full px-3 py-2 border border-border-2 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent disabled:opacity-50 disabled:cursor-not-allowed"
            :placeholder="$t('team.emailPlaceholder', 'Enter email address')"
          />
          <p v-if="errors.email" class="text-red-600 text-xs mt-1">
            {{ errors.email }}
          </p>
          <p v-if="isEditing" class="text-xs text-secondary mt-1">
            {{ $t('team.emailCannotChange', 'Email cannot be changed') }}
          </p>
        </div>

        <div v-if="!isEditing">
          <label class="block text-sm font-medium text-secondary mb-1">
            {{ $t('team.temporaryPassword', 'Temporary Password') }}
            <span class="text-red-500">*</span>
          </label>
          <div class="relative">
            <input
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              required
              :disabled="isLoading"
              class="w-full px-3 py-2 pr-10 border border-border-2 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent disabled:opacity-50 disabled:cursor-not-allowed"
              :placeholder="$t('team.passwordPlaceholder', 'Enter temporary password')"
            />
            <button
              type="button"
              @click="showPassword = !showPassword"
              class="absolute right-3 top-1/2 transform -translate-y-1/2 text-secondary hover:text-base"
              :disabled="isLoading"
            >
              <i :class="showPassword ? 'fa fa-eye-slash' : 'fa fa-eye'"></i>
            </button>
          </div>
          <div class="flex justify-between items-center mt-1">
            <p class="text-xs text-secondary">
              {{
                $t(
                  'team.passwordHelp',
                  'User will be required to change this password on first login',
                )
              }}
            </p>
            <button
              type="button"
              @click="generatePassword"
              :disabled="isLoading"
              class="text-primary hover:text-primary/80 text-xs font-medium disabled:opacity-50"
            >
              <i class="fa fa-refresh mr-1"></i>
              {{ $t('team.generatePassword', 'Generate') }}
            </button>
          </div>
          <p v-if="errors.password" class="text-red-600 text-xs mt-1">
            {{ errors.password }}
          </p>
        </div>

        <div>
          <TeamPermissionsSelect
            v-model:permissions="form.permissions"
            :can-manage-workspace="canManageWorkspace"
            :disabled="isLoading"
          />
        </div>
      </form>

      <div class="px-6 py-4 border-t border-border-2 flex justify-end gap-3">
        <button
          type="button"
          @click="$emit('cancel')"
          :disabled="isLoading"
          class="px-4 py-2 text-secondary hover:text-base transition-colors disabled:opacity-50"
        >
          {{ $t('common.cancel', 'Cancel') }}
        </button>
        <button
          @click="handleSubmit"
          :disabled="isLoading || !isFormValid"
          class="bg-primary text-white px-6 py-2 rounded-lg hover:bg-primary/80 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
        >
          <div
            v-if="isLoading"
            class="animate-spin rounded-full h-4 w-4 border-b-2 border-white"
          ></div>
          <i v-else :class="isEditing ? 'fa fa-save' : 'fa fa-user-plus'"></i>
          {{ isEditing ? $t('common.save', 'Save Changes') : $t('team.create.button', 'Add User') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, reactive, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import type {
  WorkspaceUser,
  CreateWorkspaceUserRequest,
  UpdateWorkspaceUserRequest,
} from '@/types/team'
import TeamPermissionsSelect from './TeamPermissionsSelect.vue'

const props = defineProps<{
  user?: WorkspaceUser
  isLoading?: boolean
}>()

const emit = defineEmits<{
  'create-user': [user: CreateWorkspaceUserRequest]
  'update-user': [data: { userId: number; updates: UpdateWorkspaceUserRequest }]
  cancel: []
}>()

const authStore = useAuthStore()

const isEditing = computed(() => !!props.user)

const canManageWorkspace = computed(() => {
  return authStore.hasPermission('workspace.write')
})

const form = reactive({
  firstName: '',
  lastName: '',
  username: '',
  email: '',
  password: '',
  permissions: [] as string[],
})

const showPassword = ref(false)
const errors = ref<Record<string, string>>({})

watch(
  () => props.user,
  (user) => {
    if (user) {
      form.firstName = user.first_name
      form.lastName = user.last_name
      form.username = user.username
      form.email = user.email
      form.password = ''
      form.permissions = [...(user.permissions || [])]
    } else {
      form.firstName = ''
      form.lastName = ''
      form.username = ''
      form.email = ''
      form.password = ''
      form.permissions = ['company.view'] // Default permission for new users
    }
  },
  { immediate: true },
)

const isFormValid = computed(() => {
  if (isEditing.value) {
    return form.firstName.trim() !== '' && form.lastName.trim() !== ''
  } else {
    return (
      form.firstName.trim() !== '' &&
      form.lastName.trim() !== '' &&
      form.username.trim() !== '' &&
      form.email.trim() !== '' &&
      form.password.trim() !== '' &&
      isValidEmail(form.email)
    )
  }
})

const isValidEmail = (email: string) => {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  return emailRegex.test(email)
}

const generatePassword = () => {
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

  form.password = password
    .split('')
    .sort(() => 0.5 - Math.random())
    .join('')
}

const validateForm = () => {
  errors.value = {}

  if (!form.firstName.trim()) {
    errors.value.firstName = 'First name is required'
  }

  if (!form.lastName.trim()) {
    errors.value.lastName = 'Last name is required'
  }

  if (!isEditing.value) {
    if (!form.username.trim()) {
      errors.value.username = 'Username is required'
    } else if (form.username.length < 3) {
      errors.value.username = 'Username must be at least 3 characters'
    }

    if (!form.email.trim()) {
      errors.value.email = 'Email is required'
    } else if (!isValidEmail(form.email)) {
      errors.value.email = 'Please enter a valid email address'
    }

    if (!form.password.trim()) {
      errors.value.password = 'Password is required'
    } else if (form.password.length < 8) {
      errors.value.password = 'Password must be at least 8 characters'
    }
  }

  return Object.keys(errors.value).length === 0
}

const handleSubmit = () => {
  if (!validateForm()) return

  if (isEditing.value && props.user) {
    emit('update-user', {
      userId: props.user.id,
      updates: {
        first_name: form.firstName.trim(),
        last_name: form.lastName.trim(),
        permissions: form.permissions,
      },
    })
  } else {
    emit('create-user', {
      email: form.email.trim(),
      username: form.username.trim(),
      password: form.password.trim(),
      first_name: form.firstName.trim(),
      last_name: form.lastName.trim(),
      permissions: form.permissions,
    })
  }
}
</script>
