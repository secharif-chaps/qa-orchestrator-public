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
          <Icon icon="fa-spinner" class="fa-spin text-sm" />
          {{ $t('settings.team.loadingPermissions') }}
        </span>
        <!-- Permission loaded -->
        <span v-else-if="selectedTier" class="flex items-center gap-2">
          <Icon :icon="getPermissionIcon(selectedTier)" class="text-sm" />
          {{ permissionOptions.find((p) => p.value === selectedTier)?.label }}
        </span>
        <!-- Not loaded yet -->
        <span v-else class="text-secondary">
          {{ $t('settings.team.selectPermission') }}
        </span>
        <Icon
          icon="fa-chevron-down"
          class="text-xs transition-transform"
          :class="{ 'rotate-180': isOpen }"
        />
      </button>
    </template>

    <template #content="{ close }">
      <!-- Loading state in dropdown -->
      <div v-if="isLoadingPermissions" class="py-4 text-center">
        <Icon icon="fa-spinner" class="fa-spin text-primary"></Icon>
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
          <Icon :icon="getPermissionIcon(permission.value)" class="w-4 text-base"></Icon>
          <div class="flex-1">
            <div class="font-medium">{{ permission.label }}</div>
            <div class="text-secondary text-xs">{{ permission.description }}</div>
          </div>
          <Icon
            icon="fa-check"
            v-if="selectedTier === permission.value"
            class="text-success"
          ></Icon>
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
import { Icon } from '@owlint/feathers-vue'
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
} = useQuery({
  ...memberPermissionsQuery({ userId: props.memberId }),
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
    label: t('settings.team.permissions.reader'),
    description: t('settings.team.permissions.readerDesc'),
  },
  {
    value: 'writer',
    label: t('settings.team.permissions.writer'),
    description: t('settings.team.permissions.writerDesc'),
  },
  {
    value: 'manager',
    label: t('settings.team.permissions.manager'),
    description: t('settings.team.permissions.managerDesc'),
  },
  {
    value: 'admin',
    label: t('settings.team.permissions.admin'),
    description: t('settings.team.permissions.adminDesc'),
  },
])
</script>
