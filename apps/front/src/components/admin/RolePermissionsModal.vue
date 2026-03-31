<template>
  <div
    class="bg-base-100/20 fixed inset-0 z-20 flex items-center justify-center backdrop-blur-sm"
    @click.self="$emit('close')"
  >
    <div
      class="bg-base-100 border-primary-stroke mx-4 flex max-h-[90vh] w-full max-w-2xl flex-col gap-6 overflow-y-auto rounded-xl border p-6 shadow-2xl"
    >
      <!-- Header -->
      <div class="mb-6 flex items-center justify-between">
        <div>
          <h3 class="text-lg font-semibold">
            {{ $t('admin.permissions.title') }}
          </h3>
          <p class="text-secondary mt-1 text-sm">
            {{
              $t(
                'admin.permissions.description',
                { username },
                'Configure permissions for {username}',
              )
            }}
          </p>
        </div>
        <Button variant="tertiary" icon="fa fa-times" @click="$emit('close')" />
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="py-12 text-center">
        <div class="border-primary mx-auto mb-4 h-8 w-8 animate-spin rounded-full border-b-2"></div>
        <p class="text-secondary text-sm">
          {{ $t('admin.permissions.loading') }}
        </p>
      </div>

      <!-- Error State -->
      <Alert
        v-else-if="error"
        variant="danger"
        class="mb-6"
        icon="fa-exclamation-circle"
        :title="$t('admin.permissions.error.title')"
        :description="String(error)"
      />

      <!-- Content (only shown when loaded) -->
      <template v-else-if="permissionsData">
        <!-- Warning for legacy permissions -->
        <Alert
          v-if="hasLegacy"
          variant="warning"
          class="mb-6"
          icon="fa-exclamation-triangle"
          :title="$t('admin.permissions.legacyWarning.title')"
          :description="
            $t(
              'admin.permissions.legacyWarning.description',
              'This user has old-style permissions. They will be automatically converted to the new permission model when you save.',
            )
          "
        />

        <!-- Warning for custom permissions -->
        <Alert
          v-else-if="hasCustomPermissions"
          variant="warning"
          class="mb-6"
          icon="fa-exclamation-triangle"
          :title="$t('admin.permissions.customWarning.title')"
          :description="
            $t(
              'admin.permissions.customWarning.description',
              `This user has custom permissions that don't match any predefined role. Selecting a role will override their current permissions.`,
            )
          "
        />

        <!-- Tab Selection: Roles vs Custom -->
        <div class="border-primary-stroke mb-6 flex gap-2 border-b">
          <button
            class="px-4 py-2 text-sm font-medium transition-colors"
            :class="
              mode === 'roles'
                ? 'text-sage-700 border-sage-700 border-b-2'
                : 'text-secondary hover:text-base'
            "
            @click="mode = 'roles'"
          >
            {{ $t('admin.permissions.tabs.roles') }}
          </button>
          <button
            class="px-4 py-2 text-sm font-medium transition-colors"
            :class="
              mode === 'custom'
                ? 'text-sage-700 border-sage-700 border-b-2'
                : 'text-secondary hover:text-base'
            "
            @click="mode = 'custom'"
          >
            {{ $t('admin.permissions.tabs.custom') }}
          </button>
        </div>

        <!-- Role Selection Mode -->
        <div v-if="mode === 'roles'" class="mb-6 flex flex-col gap-3">
          <RoleBlock
            v-for="role in allRoles"
            :key="role.id"
            :role="role"
            :selected="selectedRoleId === role.id"
            :disabled="role.id === 'admin' && !isChapsVisionUser"
            :disabled-reason="
              role.id === 'admin' && !isChapsVisionUser
                ? $t(
                    'admin.permissions.adminChapsVisionOnly',
                    'Admin role is reserved for ChapsVision employees',
                  )
                : undefined
            "
            @select="handleRoleSelect"
          />
        </div>

        <!-- Custom Permissions Mode -->
        <div v-else class="mb-6 flex flex-col gap-6">
          <!-- Base Access Section -->
          <div class="border-primary-stroke rounded-lg border p-4">
            <h4 class="mb-3 flex items-center gap-2 text-sm font-semibold">
              <i class="fa fa-shield text-sage-700"></i>
              {{ $t('admin.permissions.sections.base') }}
            </h4>
            <div class="flex flex-col gap-3">
              <!-- organization.read (always on) -->
              <div class="bg-base-200 flex items-center justify-between rounded-lg p-3">
                <div class="flex items-center gap-3">
                  <div
                    class="bg-sage-100 dark:bg-sage-800/30 flex h-8 w-8 items-center justify-center rounded-lg"
                  >
                    <i class="fa fa-eye text-sage-700 text-sm"></i>
                  </div>
                  <div>
                    <p class="text-sm font-medium">
                      {{ $t('admin.permissions.organizationRead.label') }}
                    </p>
                    <p class="text-secondary text-xs">
                      {{
                        $t(
                          'admin.permissions.organizationRead.description',
                          'View folders and companies in the organization',
                        )
                      }}
                    </p>
                  </div>
                </div>
                <Tag :label="$t('admin.permissions.alwaysOn')" variant="sage" size="sm" />
              </div>

              <!-- organization.write (toggle) -->
              <PermissionCheckbox
                v-model="selectedPermissions"
                permission="organization.write"
                :label="$t('admin.permissions.organizationWrite.label')"
                :description="
                  $t(
                    'admin.permissions.organizationWrite.description',
                    'Create and manage folders, manage owned content',
                  )
                "
                icon="fa-pencil"
              />

              <!-- organization.manage (toggle) -->
              <PermissionCheckbox
                v-model="selectedPermissions"
                permission="organization.manage"
                :label="$t('admin.permissions.organizationManage.label')"
                :description="
                  $t(
                    'admin.permissions.organizationManage.description',
                    'Manage team members and their permissions',
                  )
                "
                icon="fa-users-cog"
              />
            </div>
          </div>

          <!-- Module Permissions Section -->
          <div class="border-primary-stroke rounded-lg border p-4">
            <h4 class="mb-3 flex items-center gap-2 text-sm font-semibold">
              <i class="fa fa-puzzle-piece text-sage-700"></i>
              {{ $t('admin.permissions.sections.modules') }}
            </h4>
            <p class="text-secondary mb-3 text-xs">
              {{
                $t(
                  'admin.permissions.sections.modulesDescription',
                  'These permissions allow creating specific types of content. Requires Write Access.',
                )
              }}
            </p>
            <div class="flex flex-col gap-3">
              <!-- company.create -->
              <PermissionCheckbox
                v-model="selectedPermissions"
                permission="company.create"
                :label="$t('admin.permissions.companyCreate.label')"
                :description="
                  $t(
                    'admin.permissions.companyCreate.description',
                    'Add company screens and other items to folders',
                  )
                "
                icon="fa-plus-circle"
                :disabled="!hasWriteAccess"
                :disabled-reason="$t('admin.permissions.requiresWriteAccess')"
              />

              <!-- target.create -->
              <PermissionCheckbox
                v-model="selectedPermissions"
                permission="target.create"
                :label="$t('admin.permissions.targetCreate.label')"
                :description="
                  $t(
                    'admin.permissions.targetCreate.description',
                    'Create new watchfiles (Target module)',
                  )
                "
                icon="fa-bullseye"
                :disabled="!hasWriteAccess"
                :disabled-reason="$t('admin.permissions.requiresWriteAccess')"
              />
            </div>
          </div>

          <!-- Admin Permissions Section -->
          <div class="border-error-stroke rounded-lg border p-4">
            <h4 class="text-error-light-content mb-3 flex items-center gap-2 text-sm font-semibold">
              <i class="fa fa-shield-check"></i>
              {{ $t('admin.permissions.sections.admin') }}
            </h4>
            <p class="text-secondary mb-3 text-xs">
              {{
                $t(
                  'admin.permissions.sections.adminDescription',
                  'Administrative access grants full control. Use with caution.',
                )
              }}
            </p>
            <div class="flex flex-col gap-3">
              <!-- admin.organizations (with confirmation and ChapsVision restriction) -->
              <PermissionCheckbox
                v-model="selectedPermissions"
                permission="admin.organizations"
                :label="$t('admin.permissions.adminOrganizations.label')"
                :description="
                  $t(
                    'admin.permissions.adminOrganizations.description',
                    'Full administrative access to all organizations',
                  )
                "
                icon="fa-shield-check"
                variant="danger"
                :disabled="!isChapsVisionUser"
                :disabled-reason="
                  !isChapsVisionUser
                    ? $t(
                        'admin.permissions.adminChapsVisionOnly',
                        'Admin role is reserved for ChapsVision employees',
                      )
                    : undefined
                "
                @change="handleAdminPermissionChange"
              />
            </div>
          </div>

          <!-- Current Permissions Summary -->
          <div class="bg-base-200 rounded-lg p-4">
            <h4 class="mb-2 text-sm font-semibold">
              {{ $t('admin.permissions.summary.title') }}
            </h4>
            <div class="flex flex-wrap gap-2">
              <Tag
                v-for="permission in effectivePermissions"
                :key="permission"
                :label="permission"
                size="sm"
                variant="slate"
              />
              <span v-if="effectivePermissions.length === 0" class="text-secondary text-sm">
                {{ $t('admin.permissions.summary.noPermissions') }}
              </span>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex justify-end gap-3">
          <Button variant="secondary" :label="$t('common.cancel')" @click="$emit('close')" />
          <Button
            variant="primary"
            :label="$t('admin.permissions.save')"
            :disabled="!canSave"
            @click="handleSave"
          />
        </div>
      </template>
    </div>

    <!-- Confirm Admin Role Modal -->
    <ConfirmAdminRoleModal v-model="showAdminConfirmModal" @confirm="handleAdminConfirm" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useQuery } from '@pinia/colada'
import { userPermissionsQuery } from '@/queries/admin-users'
import { useRoles } from '@/composables/useRoles'
import { Alert, Button } from '@owlint/feathers-vue'
import Tag from '@/components/ui/Tag.vue'
import RoleBlock from './RoleBlock.vue'
import PermissionCheckbox from './PermissionCheckbox.vue'
import ConfirmAdminRoleModal from './ConfirmAdminRoleModal.vue'

const props = defineProps<{
  userId: string
  username: string
  userEmail: string
}>()

const emit = defineEmits<{
  confirm: [permissions: string[]]
  close: []
}>()

// Fetch user permissions on-demand
const {
  data: permissionsData,
  isLoading,
  error,
} = useQuery(() => userPermissionsQuery({ userId: props.userId }))

const { getAllRoles, getUserRole, hasLegacyPermissions, normalizePermissions } = useRoles()

const allRoles = getAllRoles()
const selectedRoleId = ref<string | null>(null)
const mode = ref<'roles' | 'custom'>('roles')

// Custom permissions state - array of selected permission strings
const selectedPermissions = ref<string[]>([])

// Admin confirmation modal state
const showAdminConfirmModal = ref(false)
const pendingAdminAction = ref<'role' | 'permission' | null>(null)
const pendingRoleId = ref<string | null>(null)

// User's current permissions from the fetched data
const userPermissions = computed(() => permissionsData.value?.permissions ?? [])

// Check if user has legacy permissions
const hasLegacy = computed(() => hasLegacyPermissions(userPermissions.value))

// Check if user has custom permissions (doesn't match any role)
const hasCustomPermissions = computed(() => {
  return getUserRole(userPermissions.value) === null && !hasLegacy.value
})

// Check if write access is enabled (for module permission dependencies)
const hasWriteAccess = computed(() => selectedPermissions.value.includes('organization.write'))

// Check if user is ChapsVision employee (case-insensitive)
const isChapsVisionUser = computed(() => {
  return props.userEmail.toLowerCase().endsWith('@chapsvision.com')
})

// Effective permissions (always includes organization.read)
const effectivePermissions = computed(() => {
  const perms = new Set(selectedPermissions.value)
  perms.add('organization.read') // Always included
  return Array.from(perms).sort()
})

// Can save check
const canSave = computed(() => {
  if (mode.value === 'roles') {
    return selectedRoleId.value !== null
  }
  return true // Custom mode always allows save (at minimum organization.read)
})

// Initialize selected role and permissions when permissions data is loaded
watch(
  permissionsData,
  (data) => {
    if (!data) return

    // Normalize permissions first (handle legacy)
    const normalized = normalizePermissions(data.permissions)

    // Try to match user's current permissions to a role
    const matchedRole = getUserRole(data.permissions)
    selectedRoleId.value = matchedRole?.id ?? null

    // Initialize custom permissions
    selectedPermissions.value = normalized.filter((p) => p !== 'organization.read')
  },
  { immediate: true },
)

// Sync role selection to custom permissions
watch(selectedRoleId, (newRoleId) => {
  if (newRoleId && mode.value === 'roles') {
    const role = allRoles.find((r) => r.id === newRoleId)
    if (role) {
      selectedPermissions.value = role.permissions.filter((p) => p !== 'organization.read')
    }
  }
})

// When switching to custom mode, clear role selection if permissions don't match
watch(mode, (newMode) => {
  if (newMode === 'custom') {
    // Check if current permissions still match the selected role
    const currentPerms = effectivePermissions.value
    const matchedRole = getUserRole(currentPerms)
    if (matchedRole?.id !== selectedRoleId.value) {
      selectedRoleId.value = null
    }
  }
})

// Handle role selection with admin confirmation
function handleRoleSelect(roleId: string) {
  const role = allRoles.find((r) => r.id === roleId)

  // Check if selecting Admin role
  if (role?.id === 'admin') {
    pendingRoleId.value = roleId
    pendingAdminAction.value = 'role'
    showAdminConfirmModal.value = true
  } else {
    selectedRoleId.value = roleId
  }
}

// Handle admin permission toggle with confirmation
function handleAdminPermissionChange(checked: boolean) {
  if (checked) {
    pendingAdminAction.value = 'permission'
    showAdminConfirmModal.value = true
  } else {
    // Remove permission without confirmation
    selectedPermissions.value = selectedPermissions.value.filter((p) => p !== 'admin.organizations')
  }
}

// Confirm admin role/permission
const handleAdminConfirm = () => {
  if (pendingAdminAction.value === 'role' && pendingRoleId.value) {
    selectedRoleId.value = pendingRoleId.value
  } else if (pendingAdminAction.value === 'permission') {
    selectedPermissions.value = [...selectedPermissions.value, 'admin.organizations']
  }

  pendingAdminAction.value = null
  pendingRoleId.value = null
}

// Clean up when modal closes without confirmation (cancel/click outside)
watch(showAdminConfirmModal, (isOpen) => {
  if (!isOpen && pendingAdminAction.value !== null) {
    selectedPermissions.value = selectedPermissions.value.filter((p) => p !== 'admin.organizations')
    pendingAdminAction.value = null
    pendingRoleId.value = null
  }
})

function handleSave() {
  if (mode.value === 'roles' && selectedRoleId.value) {
    // Get permissions for selected role
    const role = allRoles.find((r) => r.id === selectedRoleId.value)
    if (role) {
      emit('confirm', role.permissions)
    }
  } else {
    // Use effective permissions from custom mode
    emit('confirm', effectivePermissions.value)
  }
}
</script>
