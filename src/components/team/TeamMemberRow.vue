<template>
  <div class="px-6 py-4 hover:bg-base-200/50 transition-colors">
    <div class="grid grid-cols-12 gap-4 items-center">
      <!-- Avatar + Name (col-span-4) -->
      <div class="col-span-4 flex items-center gap-3">
        <!-- Circular Avatar -->
        <div
          class="w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center font-semibold text-sm"
        >
          {{ memberInitials }}
        </div>

        <!-- Name + Username -->
        <div class="flex-1 min-w-0">
          <div class="font-medium flex items-center gap-2">
            {{ memberDisplayName }}
            <Tag v-if="member.is_current_user" variant="primary" :label="t('settings.team.you', 'You')" size="xs" rounded />
          </div>
          <div class="text-sm text-secondary">@{{ member.username }}</div>
        </div>
      </div>

      <!-- Email (col-span-3) -->
      <div class="col-span-3">
        <div class="text-sm text-secondary truncate">{{ member.email }}</div>
      </div>

      <!-- Permission Tier Select (col-span-3) -->
      <div class="col-span-3">
        <Dropdown align="left" width="full" :close-on-select="true" @open="fetchPermissions">
          <template #trigger="{ isOpen }">
            <button
              type="button"
              class="flex items-center justify-between w-full px-3 py-2 text-sm border border-base-300 rounded-lg bg-base-100 hover:bg-base-200 transition-colors min-w-48"
              :class="{
                'ring-2 ring-primary': isOpen,
                'opacity-50 cursor-not-allowed': !canManage,
              }"
              :disabled="!canManage"
            >
              <!-- Loading state -->
              <span v-if="isLoadingPermissions" class="flex items-center gap-2 text-secondary">
                <i class="fa fa-spinner fa-spin text-sm"></i>
                {{ t('settings.team.loadingPermissions', 'Loading...') }}
              </span>
              <!-- Permission loaded -->
              <span v-else-if="selectedTier" class="flex items-center gap-2">
                <i :class="getPermissionIcon(selectedTier)" class="text-sm"></i>
                {{ permissionOptions.find((p) => p.value === selectedTier)?.label }}
              </span>
              <!-- Not loaded yet -->
              <span v-else class="text-secondary">
                {{ t('settings.team.selectPermission', 'Select permission...') }}
              </span>
              <i
                class="fa fa-chevron-down text-xs transition-transform"
                :class="{ 'rotate-180': isOpen }"
              ></i>
            </button>
          </template>

          <template #content="{ close }">
            <!-- Loading state in dropdown -->
            <div v-if="isLoadingPermissions" class="py-4 text-center">
              <i class="fa fa-spinner fa-spin text-primary"></i>
            </div>
            <!-- Permission options -->
            <div v-else class="py-1">
              <button
                v-for="permission in permissionOptions"
                :key="permission.value"
                type="button"
                class="w-full flex items-center gap-3 px-4 py-2.5 text-left hover:bg-base-200 transition-colors"
                :class="{ 'bg-primary-light': selectedTier === permission.value }"
                @click="selectPermission(permission.value, close)"
              >
                <i :class="getPermissionIcon(permission.value)" class="text-base w-4"></i>
                <div class="flex-1">
                  <div class="font-medium">{{ permission.label }}</div>
                  <div class="text-xs text-secondary">{{ permission.description }}</div>
                </div>
                <i v-if="selectedTier === permission.value" class="fa fa-check text-success"></i>
              </button>
            </div>
          </template>
        </Dropdown>
      </div>

      <!-- Actions Menu (col-span-2) -->
      <div class="col-span-2 flex justify-end">
        <Button
          v-if="canManage"
          variant="tertiary"
          icon="fa fa-key"
          size="sm"
          title="Reset Password"
          @click="$emit('reset-password', member)"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, type ComputedRef } from 'vue'
import { useQuery } from '@pinia/colada'
import Dropdown from '@/components/ui/Dropdown.vue'
import { Tag, Button } from '@owlint/feathers-vue'
import { useI18n } from 'vue-i18n'
import { memberPermissionsQuery } from '@/queries/team'
import type { TeamMemberListItem, PermissionTier } from '@/types/team'

const { t } = useI18n()

const props = defineProps<{
  member: TeamMemberListItem
  canManage: boolean
}>()

const emit = defineEmits<{
  'update-permissions': [userId: string, tier: PermissionTier]
  'reset-password': [member: TeamMemberListItem]
}>()

// Track if permissions have been fetched
const permissionsFetched = ref(false)

// Query for permissions (lazy-loaded when dropdown opens)
const {
  data: permissionsData,
  isLoading: isLoadingPermissions,
  refetch: refetchPermissions,
} = useQuery(
  memberPermissionsQuery,
  () => ({ userId: props.member.id }),
  {
    enabled: () => permissionsFetched.value,
  }
)

// Computed property that reflects the member's permission tier (from lazy-loaded data)
const selectedTier = computed(() => permissionsData.value?.permission_tier)

// Fetch permissions when dropdown opens
const fetchPermissions = () => {
  if (!permissionsFetched.value) {
    permissionsFetched.value = true
  } else {
    // Refetch if already fetched (in case permissions changed)
    refetchPermissions()
  }
}

// Function to handle permission selection
function selectPermission(tier: PermissionTier, closeDropdown: () => void) {
  emit('update-permissions', props.member.id, tier)
  closeDropdown()
}

// Function to get icon for each permission tier
function getPermissionIcon(tier: PermissionTier): string {
  const icons: Record<PermissionTier, string> = {
    reader: 'fa fa-eye',
    writer: 'fa fa-pen',
    manager: 'fa fa-user-shield',
  }
  return icons[tier]
}

const memberInitials = computed(() => {
  if (props.member.first_name && props.member.last_name) {
    return `${props.member.first_name[0]}${props.member.last_name[0]}`.toUpperCase()
  }
  return props.member.username.substring(0, 2).toUpperCase()
})

const memberDisplayName = computed(() => {
  if (props.member.first_name && props.member.last_name) {
    return `${props.member.first_name} ${props.member.last_name}`
  }
  return props.member.username
})

const permissionOptions: ComputedRef<
  { value: PermissionTier; label: string; description: string }[]
> = computed(() => [
  {
    value: 'reader',
    label: t('settings.team.permissions.reader', 'Reader'),
    description: t('settings.team.permissions.readerDesc', 'View only'),
  },
  {
    value: 'writer',
    label: t('settings.team.permissions.writer', 'Writer'),
    description: t('settings.team.permissions.writerDesc', 'Create and manage content'),
  },
  {
    value: 'manager',
    label: t('settings.team.permissions.manager', 'Manager'),
    description: t('settings.team.permissions.managerDesc', 'Full team management'),
  },
])
</script>
