<template>
  <Dropdown align="left" width="full" :close-on-select="true" @open="fetchPermissions">
    <template #trigger="{ isOpen }">
      <button
        type="button"
        class="border-base-300 bg-base-100 hover:bg-base-200 flex w-full min-w-48 items-center justify-between rounded-lg border px-3 py-2 text-sm transition-colors"
        :class="{
          'ring-primary ring-2': isOpen,
          'cursor-not-allowed opacity-50': !canManage,
        }"
        :disabled="!canManage"
      >
        <!-- Loading state -->
        <span v-if="isLoadingPermissions" class="text-secondary flex items-center gap-2">
          <i class="fa fa-spinner fa-spin text-sm"></i>
          {{ $t('settings.team.loadingPermissions', 'Loading...') }}
        </span>
        <!-- Permission loaded -->
        <span v-else-if="selectedTier" class="flex items-center gap-2">
          <i :class="getPermissionIcon(selectedTier)" class="text-sm"></i>
          {{ permissionOptions.find((p) => p.value === selectedTier)?.label }}
        </span>
        <!-- Not loaded yet -->
        <span v-else class="text-secondary">
          {{ $t('settings.team.selectPermission', 'Select permission...') }}
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
          class="hover:bg-base-200 flex w-full items-center gap-3 px-4 py-2.5 text-left transition-colors"
          :class="{ 'bg-primary-light': selectedTier === permission.value }"
          @click="selectPermission(permission.value, close)"
        >
          <i :class="getPermissionIcon(permission.value)" class="w-4 text-base"></i>
          <div class="flex-1">
            <div class="font-medium">{{ permission.label }}</div>
            <div class="text-secondary text-xs">{{ permission.description }}</div>
          </div>
          <i v-if="selectedTier === permission.value" class="fa fa-check text-success"></i>
        </button>
      </div>
    </template>
  </Dropdown>
</template>

<script setup lang="ts">
/**
 * Permission dropdown with lazy-loading.
 * Fetches permissions from API only when dropdown is opened.
 */
import Dropdown from '@/components/ui/Dropdown.vue'
import { memberPermissionsQuery } from '@/queries/team'
import type { PermissionTier } from '@/types/team'
import { useQuery } from '@pinia/colada'
import { computed, ref, type ComputedRef } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const props = defineProps<{
  memberId: string
  canManage: boolean
}>()

const emit = defineEmits<{
  'update-permissions': [tier: PermissionTier]
}>()

// Track if permissions have been fetched
const permissionsFetched = ref(false)

// Query for permissions (lazy-loaded when dropdown opens)
const {
  data: permissionsData,
  isLoading: isLoadingPermissions,
  refetch: refetchPermissions,
} = useQuery(memberPermissionsQuery, () => ({ userId: props.memberId }), {
  enabled: () => permissionsFetched.value,
})

// Computed property that reflects the member's permission tier
const selectedTier = computed(() => permissionsData.value?.permission_tier)

// Fetch permissions when dropdown opens
const fetchPermissions = () => {
  if (!permissionsFetched.value) {
    permissionsFetched.value = true
  } else {
    refetchPermissions()
  }
}

// Handle permission selection
const selectPermission = (tier: PermissionTier, closeDropdown: () => void) => {
  emit('update-permissions', tier)
  closeDropdown()
}

// Get icon for each permission tier
const getPermissionIcon = (tier: PermissionTier): string => {
  const icons: Record<PermissionTier, string> = {
    reader: 'fa-eye',
    writer: 'fa-pen',
    manager: 'fa-user-shield',
    admin: 'fa-crown',
  }
  return icons[tier]
}

// Permission options
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
