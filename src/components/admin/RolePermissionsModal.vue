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
          <h3 class="text-lg font-semibold">Manage Permissions</h3>
          <p class="text-sm text-secondary mt-1">
            Select a role for <span class="font-semibold">{{ user.username }}</span>
          </p>
        </div>
        <Button variant="tertiary" icon="fa fa-times" @click="$emit('close')" />
      </div>

      <!-- Warning for custom permissions -->
      <Alert
        v-if="hasCustomPermissions"
        variant="warning"
        class="mb-6"
        icon="fa fa-exclamation-triangle"
      >
        <div>
          <p class="font-semibold">Custom Permissions Detected</p>
          <p class="text-sm">
            This user has custom permissions that don't match any predefined role. Selecting a role
            will override their current permissions.
          </p>
        </div>
      </Alert>

      <!-- Role Selection -->
      <div class="flex flex-col gap-3 mb-6">
        <RoleBlock
          v-for="role in allRoles"
          :key="role.id"
          :role="role"
          :selected="selectedRoleId === role.id"
          @select="selectedRoleId = $event"
        />
      </div>

      <!-- Actions -->
      <div class="flex justify-end gap-3">
        <Button variant="secondary" label="Cancel" @click="$emit('close')" />
        <Button variant="primary" label="Save Permissions" :disabled="!selectedRoleId" @click="handleSave" />
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
import RoleBlock from './RoleBlock.vue'

const props = defineProps<{
  user: AdminUserResponse
}>()

const emit = defineEmits<{
  confirm: [permissions: string[]]
  close: []
}>()

const { getAllRoles, getUserRole, getPermissionsForRole } = useRoles()

const allRoles = getAllRoles()
const selectedRoleId = ref<string | null>(null)

// Check if user has custom permissions (doesn't match any role)
const hasCustomPermissions = computed(() => {
  return getUserRole(props.user.permissions) === null
})

// Initialize selected role when user changes
watch(
  () => props.user.user_id,
  () => {
    // Try to match user's current permissions to a role
    const matchedRole = getUserRole(props.user.permissions)
    selectedRoleId.value = matchedRole?.id ?? null
  },
  { immediate: true },
)

function handleSave() {
  if (!selectedRoleId.value) return

  // Get permissions for selected role
  const role = allRoles.find((r) => r.id === selectedRoleId.value)
  if (!role) return

  emit('confirm', role.permissions)
}
</script>
