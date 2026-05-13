<template>
  <Modal
    :display-modal="true"
    :title="$t('admin.permissions.title')"
    icon="fa-shield"
    size="xl"
    color=""
    @close="emit('close')"
  >
    <template #description>
      <div class="flex flex-col gap-6">
        <p class="text-neutral-black-font">
          {{ $t('admin.permissions.description', { username }) }}
        </p>

        <!-- Loading State -->
        <div v-if="isLoading" class="py-12 text-center">
          <div
            class="border-primary mx-auto mb-4 h-8 w-8 animate-spin rounded-full border-b-2"
          ></div>
          <p class="text-neutral-black-font text-sm">
            {{ $t('admin.permissions.loading') }}
          </p>
        </div>

        <!-- Error State -->
        <Alert
          v-else-if="error"
          variant="danger"
          icon="fa-exclamation-circle"
          :title="$t('admin.permissions.error.title')"
          :description="String(error)"
        />

        <!-- Content (only shown when loaded) -->
        <div v-else-if="permissionsData" class="flex flex-col gap-6">
          <!-- Warning for legacy permissions -->
          <Alert
            v-if="hasLegacy"
            variant="warning"
            icon="fa-exclamation-triangle"
            :title="$t('admin.permissions.legacyWarning.title')"
            :description="$t('admin.permissions.legacyWarning.description')"
          />

          <!-- Warning for custom permissions -->
          <Alert
            v-else-if="hasCustomPermissions"
            variant="warning"
            icon="fa-exclamation-triangle"
            :title="$t('admin.permissions.customWarning.title')"
            :description="$t('admin.permissions.customWarning.description')"
          />

          <!-- Tab Selection: Roles vs Custom -->
          <Tab :tabs="modeTabs" variant="inner" />

          <!-- Role Selection Mode -->
          <div v-if="mode === 'roles'" class="flex flex-col gap-3">
            <RoleBlock
              v-for="role in allRoles"
              :key="role.id"
              :role="role"
              :selected="selectedRoleId === role.id"
              :disabled="role.id === 'admin' && !isChapsVisionUser"
              :disabled-reason="
                role.id === 'admin' && !isChapsVisionUser
                  ? $t('admin.permissions.adminChapsVisionOnly')
                  : undefined
              "
              @select="handleRoleSelect"
            />
          </div>

          <!-- Custom Permissions Mode -->
          <div v-else class="flex flex-col gap-6">
            <!-- Base Access Section -->
            <div class="border-primary-lighter-stroke rounded-sm border p-4">
              <h4 class="mb-3 flex items-center gap-2 text-sm font-semibold">
                <i class="fa fa-shield text-sage-700"></i>
                {{ $t('admin.permissions.sections.base') }}
              </h4>
              <div class="flex flex-col gap-3">
                <!-- organization.read (always on) -->
                <div class="bg-primary-lightest flex items-center justify-between rounded-sm p-3">
                  <div class="flex items-center gap-3">
                    <div
                      class="bg-sage-100 dark:bg-sage-800/30 flex h-8 w-8 items-center justify-center rounded-sm"
                    >
                      <i class="fa fa-eye text-sage-700 text-sm"></i>
                    </div>
                    <div>
                      <p class="text-sm font-medium">
                        {{ $t('admin.permissions.organizationRead.label') }}
                      </p>
                      <p class="text-neutral-black-font text-xs">
                        {{ $t('admin.permissions.organizationRead.description') }}
                      </p>
                    </div>
                  </div>
                  <Tag :label="$t('admin.permissions.alwaysOn')" color="sage" size="sm" />
                </div>

                <!-- organization.write (toggle) -->
                <PermissionCheckbox
                  v-model="selectedPermissions"
                  permission="organization.write"
                  :label="$t('admin.permissions.organizationWrite.label')"
                  :description="$t('admin.permissions.organizationWrite.description')"
                  icon="fa-pencil"
                />

                <!-- organization.manage (toggle) -->
                <PermissionCheckbox
                  v-model="selectedPermissions"
                  permission="organization.manage"
                  :label="$t('admin.permissions.organizationManage.label')"
                  :description="$t('admin.permissions.organizationManage.description')"
                  icon="fa-users-cog"
                />
              </div>
            </div>

            <!-- Module Permissions Section -->
            <div class="border-primary-lighter-stroke rounded-sm border p-4">
              <h4 class="mb-3 flex items-center gap-2 text-sm font-semibold">
                <i class="fa fa-puzzle-piece text-sage-700"></i>
                {{ $t('admin.permissions.sections.modules') }}
              </h4>
              <p class="text-neutral-black-font mb-3 text-xs">
                {{ $t('admin.permissions.sections.modulesDescription') }}
              </p>
              <div class="flex flex-col gap-3">
                <!-- company.create -->
                <PermissionCheckbox
                  v-model="selectedPermissions"
                  permission="company.create"
                  :label="$t('admin.permissions.companyCreate.label')"
                  :description="$t('admin.permissions.companyCreate.description')"
                  icon="fa-plus-circle"
                  :disabled="!hasWriteAccess"
                  :disabled-reason="$t('admin.permissions.requiresWriteAccess')"
                />

                <!-- target.create -->
                <PermissionCheckbox
                  v-model="selectedPermissions"
                  permission="target.create"
                  :label="$t('admin.permissions.targetCreate.label')"
                  :description="$t('admin.permissions.targetCreate.description')"
                  icon="fa-bullseye"
                  :disabled="!hasWriteAccess"
                  :disabled-reason="$t('admin.permissions.requiresWriteAccess')"
                />
              </div>
            </div>

            <!-- Admin Permissions Section -->
            <div class="border-error-stroke rounded-sm border p-4">
              <h4
                class="text-error-light-content mb-3 flex items-center gap-2 text-sm font-semibold"
              >
                <i class="fa fa-shield-check"></i>
                {{ $t('admin.permissions.sections.admin') }}
              </h4>
              <p class="text-neutral-black-font mb-3 text-xs">
                {{ $t('admin.permissions.sections.adminDescription') }}
              </p>
              <div class="flex flex-col gap-3">
                <!-- admin.organizations (with confirmation and ChapsVision restriction) -->
                <PermissionCheckbox
                  v-model="selectedPermissions"
                  permission="admin.organizations"
                  :label="$t('admin.permissions.adminOrganizations.label')"
                  :description="$t('admin.permissions.adminOrganizations.description')"
                  icon="fa-shield-check"
                  variant="danger"
                  :disabled="!isChapsVisionUser"
                  :disabled-reason="
                    !isChapsVisionUser ? $t('admin.permissions.adminChapsVisionOnly') : undefined
                  "
                  @change="handleAdminPermissionChange"
                />
              </div>
            </div>

            <!-- Current Permissions Summary -->
            <div class="bg-primary-lightest rounded-sm p-4">
              <h4 class="mb-2 text-sm font-semibold">
                {{ $t('admin.permissions.summary.title') }}
              </h4>
              <div class="flex flex-wrap gap-2">
                <Tag
                  v-for="permission in effectivePermissions"
                  :key="permission"
                  :label="permission"
                  size="sm"
                  intent="neutral"
                />
                <span
                  v-if="effectivePermissions.length === 0"
                  class="text-neutral-black-font text-sm"
                >
                  {{ $t('admin.permissions.summary.noPermissions') }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <template #footer>
      <Button
        variant="primary"
        :label="$t('admin.permissions.save')"
        :disabled="!canSave"
        @click="handleSave"
      />
      <Button variant="secondary" :label="$t('common.cancel')" @click="emit('close')" />
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { useConfirmModal } from '@/composables/useConfirmModal'
import { useRoles } from '@/composables/useRoles'
import { userPermissionsQuery } from '@/queries/admin-users'
import { Alert, Button, Modal, Tab, Tag, type NavigationTab } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import PermissionCheckbox from './PermissionCheckbox.vue'
import RoleBlock from './RoleBlock.vue'

const { t } = useI18n()
const { showConfirmModal } = useConfirmModal()

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

const modeTabs = computed<NavigationTab[]>(() => [
  {
    id: 'roles',
    title: t('admin.permissions.tabs.roles'),
    isActive: mode.value === 'roles',
    click: () => {
      mode.value = 'roles'
    },
  },
  {
    id: 'custom',
    title: t('admin.permissions.tabs.custom'),
    isActive: mode.value === 'custom',
    click: () => {
      mode.value = 'custom'
    },
  },
])

// Custom permissions state - array of selected permission strings
const selectedPermissions = ref<string[]>([])

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
  perms.add('organization.read')
  return Array.from(perms).sort()
})

const canSave = computed(() => {
  if (mode.value === 'roles') {
    return selectedRoleId.value !== null
  }
  return true
})

// Initialize selected role and permissions when permissions data is loaded
watch(
  permissionsData,
  (data) => {
    if (!data) return

    const normalized = normalizePermissions(data.permissions)

    const matchedRole = getUserRole(data.permissions)
    selectedRoleId.value = matchedRole?.id ?? null

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
    const currentPerms = effectivePermissions.value
    const matchedRole = getUserRole(currentPerms)
    if (matchedRole?.id !== selectedRoleId.value) {
      selectedRoleId.value = null
    }
  }
})

// Granting admin needs an explicit confirmation step. The permission flow is
// "eager": the checkbox already pushed `admin.organizations` into the array
// before we open the confirm, so onCancel must roll it back. The role flow is
// "lazy": we delay applying the role until the user confirms.
const requestAdminConfirmation = (action: 'role' | 'permission', roleId?: string) => {
  showConfirmModal({
    title: t('admin.organization.admin.confirmAdminRole.title'),
    titleIcon: 'fa-exclamation-triangle',
    message: t('admin.organization.admin.confirmAdminRole.description'),
    type: 'warning',
    confirmVariant: 'accent',
    warningSection: {
      title: t('admin.organization.admin.confirmAdminRole.warningTitle'),
      message: t('admin.organization.admin.confirmAdminRole.warningDescription'),
      icon: 'fa-shield',
    },
    infoSection: {
      items: [t('admin.organization.admin.confirmAdminRole.permissions.adminOrganizations')],
    },
    onConfirm: () => {
      if (action === 'role' && roleId) {
        selectedRoleId.value = roleId
      } else if (action === 'permission') {
        selectedPermissions.value = [...selectedPermissions.value, 'admin.organizations']
      }
    },
    onCancel: () => {
      if (action === 'permission') {
        selectedPermissions.value = selectedPermissions.value.filter(
          (p) => p !== 'admin.organizations',
        )
      }
    },
  })
}

const handleRoleSelect = (roleId: string) => {
  const role = allRoles.find((r) => r.id === roleId)

  if (role?.id === 'admin') {
    requestAdminConfirmation('role', roleId)
  } else {
    selectedRoleId.value = roleId
  }
}

const handleAdminPermissionChange = (checked: boolean) => {
  if (checked) {
    requestAdminConfirmation('permission')
  } else {
    selectedPermissions.value = selectedPermissions.value.filter((p) => p !== 'admin.organizations')
  }
}

function handleSave() {
  if (mode.value === 'roles' && selectedRoleId.value) {
    const role = allRoles.find((r) => r.id === selectedRoleId.value)
    if (role) {
      emit('confirm', role.permissions)
    }
  } else {
    emit('confirm', effectivePermissions.value)
  }
}
</script>
