<template>
  <div
    class="fixed inset-0 bg-base-100/20 backdrop-blur-sm flex items-center justify-center z-50"
    @click.self="$emit('close')"
  >
    <div
      class="bg-base-100 rounded-xl shadow-2xl border border-primary-stroke p-6 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto"
    >
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <div>
          <h3 class="text-lg font-semibold">
            {{ $t('admin.permissions.title', 'Manage Permissions') }}
          </h3>
          <p class="text-sm text-secondary mt-1">
            {{
              $t(
                'admin.permissions.description',
                { username: user.username },
                'Configure permissions for {username}',
              )
            }}
          </p>
        </div>
        <Button variant="tertiary" icon="fa fa-times" @click="$emit('close')" />
      </div>

      <!-- Warning for legacy permissions -->
      <Alert v-if="hasLegacy" variant="warning" class="mb-6" icon="fa fa-exclamation-triangle">
        <div>
          <p class="font-semibold">
            {{ $t('admin.permissions.legacyWarning.title', 'Legacy Permissions Detected') }}
          </p>
          <p class="text-sm">
            {{
              $t(
                'admin.permissions.legacyWarning.description',
                'This user has old-style permissions. They will be automatically converted to the new permission model when you save.',
              )
            }}
          </p>
        </div>
      </Alert>

      <!-- Warning for custom permissions -->
      <Alert
        v-else-if="hasCustomPermissions"
        variant="warning"
        class="mb-6"
        icon="fa fa-exclamation-triangle"
      >
        <div>
          <p class="font-semibold">
            {{ $t('admin.permissions.customWarning.title', 'Custom Permissions Detected') }}
          </p>
          <p class="text-sm">
            {{
              $t(
                'admin.permissions.customWarning.description',
                "This user has custom permissions that don't match any predefined role. Selecting a role will override their current permissions.",
              )
            }}
          </p>
        </div>
      </Alert>

      <!-- Tab Selection: Roles vs Custom -->
      <div class="flex gap-2 mb-6 border-b border-primary-stroke">
        <button
          class="px-4 py-2 text-sm font-medium transition-colors"
          :class="
            mode === 'roles'
              ? 'text-sage-700 border-b-2 border-sage-700'
              : 'text-secondary hover:text-base'
          "
          @click="mode = 'roles'"
        >
          {{ $t('admin.permissions.tabs.roles', 'Quick Roles') }}
        </button>
        <button
          class="px-4 py-2 text-sm font-medium transition-colors"
          :class="
            mode === 'custom'
              ? 'text-sage-700 border-b-2 border-sage-700'
              : 'text-secondary hover:text-base'
          "
          @click="mode = 'custom'"
        >
          {{ $t('admin.permissions.tabs.custom', 'Custom Permissions') }}
        </button>
      </div>

      <!-- Role Selection Mode -->
      <div v-if="mode === 'roles'" class="flex flex-col gap-3 mb-6">
        <RoleBlock
          v-for="role in allRoles"
          :key="role.id"
          :role="role"
          :selected="selectedRoleId === role.id"
          @select="handleRoleSelect"
        />
      </div>

      <!-- Custom Permissions Mode -->
      <div v-else class="flex flex-col gap-6 mb-6">
        <!-- Base Access Section -->
        <div class="rounded-lg border border-primary-stroke p-4">
          <h4 class="text-sm font-semibold mb-3 flex items-center gap-2">
            <i class="fa fa-shield text-sage-700"></i>
            {{ $t('admin.permissions.sections.base', 'Base Access') }}
          </h4>
          <div class="flex flex-col gap-3">
            <!-- organization.read (always on) -->
            <div class="flex items-center justify-between p-3 bg-base-200 rounded-lg">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-sage-100 dark:bg-sage-800/30 flex items-center justify-center">
                  <i class="fa fa-eye text-sage-700 text-sm"></i>
                </div>
                <div>
                  <p class="font-medium text-sm">
                    {{ $t('admin.permissions.organizationRead.label', 'Read Access') }}
                  </p>
                  <p class="text-xs text-secondary">
                    {{
                      $t(
                        'admin.permissions.organizationRead.description',
                        'View folders and companies in the organization',
                      )
                    }}
                  </p>
                </div>
              </div>
              <Tag :label="$t('admin.permissions.alwaysOn', 'Always On')" variant="sage" size="sm" />
            </div>

            <!-- organization.write (toggle) -->
            <PermissionCheckbox
              v-model="selectedPermissions"
              permission="organization.write"
              :label="$t('admin.permissions.organizationWrite.label', 'Write Access')"
              :description="
                $t(
                  'admin.permissions.organizationWrite.description',
                  'Create and manage folders, manage owned content',
                )
              "
              icon="fa-pencil"
            />
          </div>
        </div>

        <!-- Module Permissions Section -->
        <div class="rounded-lg border border-primary-stroke p-4">
          <h4 class="text-sm font-semibold mb-3 flex items-center gap-2">
            <i class="fa fa-puzzle-piece text-sage-700"></i>
            {{ $t('admin.permissions.sections.modules', 'Module Permissions') }}
          </h4>
          <p class="text-xs text-secondary mb-3">
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
              :label="$t('admin.permissions.companyCreate.label', 'Add Items')"
              :description="
                $t(
                  'admin.permissions.companyCreate.description',
                  'Add company screens and other items to folders',
                )
              "
              icon="fa-plus-circle"
              :disabled="!hasWriteAccess"
              :disabled-reason="
                $t(
                  'admin.permissions.requiresWriteAccess',
                  'Requires Write Access to be enabled',
                )
              "
            />

            <!-- target.create -->
            <PermissionCheckbox
              v-model="selectedPermissions"
              permission="target.create"
              :label="$t('admin.permissions.targetCreate.label', 'Create Targets')"
              :description="
                $t(
                  'admin.permissions.targetCreate.description',
                  'Create new watchfiles (Target module)',
                )
              "
              icon="fa-bullseye"
              :disabled="!hasWriteAccess"
              :disabled-reason="
                $t(
                  'admin.permissions.requiresWriteAccess',
                  'Requires Write Access to be enabled',
                )
              "
            />
          </div>
        </div>

        <!-- Admin Permissions Section -->
        <div class="rounded-lg border border-error-stroke p-4">
          <h4 class="text-sm font-semibold mb-3 flex items-center gap-2 text-error-light-content">
            <i class="fa fa-shield-check"></i>
            {{ $t('admin.permissions.sections.admin', 'Admin Permissions') }}
          </h4>
          <p class="text-xs text-secondary mb-3">
            {{
              $t(
                'admin.permissions.sections.adminDescription',
                'Administrative access grants full control. Use with caution.',
              )
            }}
          </p>
          <div class="flex flex-col gap-3">
            <!-- admin.organizations -->
            <PermissionCheckbox
              v-model="selectedPermissions"
              permission="admin.organizations"
              :label="$t('admin.permissions.adminOrganizations.label', 'Organization Admin')"
              :description="
                $t(
                  'admin.permissions.adminOrganizations.description',
                  'Full administrative access to all organizations',
                )
              "
              icon="fa-shield-check"
              variant="danger"
            />
          </div>
        </div>

        <!-- Current Permissions Summary -->
        <div class="rounded-lg bg-base-200 p-4">
          <h4 class="text-sm font-semibold mb-2">
            {{ $t('admin.permissions.summary.title', 'Permissions Summary') }}
          </h4>
          <div class="flex flex-wrap gap-2">
            <Tag
              v-for="permission in effectivePermissions"
              :key="permission"
              :label="permission"
              size="sm"
              variant="slate"
            />
            <span v-if="effectivePermissions.length === 0" class="text-sm text-secondary">
              {{ $t('admin.permissions.summary.noPermissions', 'No permissions selected') }}
            </span>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex justify-end gap-3">
        <Button
          variant="secondary"
          :label="$t('common.cancel', 'Cancel')"
          @click="$emit('close')"
        />
        <Button
          variant="primary"
          :label="$t('admin.permissions.save', 'Save Permissions')"
          :disabled="!canSave"
          @click="handleSave"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import type { AdminUserResponse } from '@/types/admin-user'
import { useRoles } from '@/composables/useRoles'
import { Button } from '@owlint/feathers-vue'
import Alert from '@/components/ui/Alert.vue'
import Tag from '@/components/ui/Tag.vue'
import RoleBlock from './RoleBlock.vue'
import PermissionCheckbox from './PermissionCheckbox.vue'

const props = defineProps<{
  user: AdminUserResponse
}>()

const emit = defineEmits<{
  confirm: [permissions: string[]]
  close: []
}>()

const { getAllRoles, getUserRole, hasLegacyPermissions, normalizePermissions } = useRoles()

const allRoles = getAllRoles()
const selectedRoleId = ref<string | null>(null)
const mode = ref<'roles' | 'custom'>('roles')

// Custom permissions state - array of selected permission strings
const selectedPermissions = ref<string[]>([])

// Check if user has legacy permissions
const hasLegacy = computed(() => hasLegacyPermissions(props.user.permissions))

// Check if user has custom permissions (doesn't match any role)
const hasCustomPermissions = computed(() => {
  return getUserRole(props.user.permissions) === null && !hasLegacy.value
})

// Check if write access is enabled (for module permission dependencies)
const hasWriteAccess = computed(() => selectedPermissions.value.includes('organization.write'))

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

// Initialize selected role and permissions when user changes
watch(
  () => props.user.user_id,
  () => {
    // Normalize permissions first (handle legacy)
    const normalized = normalizePermissions(props.user.permissions)

    // Try to match user's current permissions to a role
    const matchedRole = getUserRole(props.user.permissions)
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

function handleRoleSelect(roleId: string) {
  selectedRoleId.value = roleId
}

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
