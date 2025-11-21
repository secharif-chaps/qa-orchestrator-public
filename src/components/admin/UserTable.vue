<template>
  <div class="bg-base-100 rounded-lg shadow-sm overflow-hidden border border-primary-stroke">
    <UserTableHeader />

    <div class="divide-y divide-primary-stroke">
      <UserTableRow
        v-for="user in users"
        :key="user.user_id"
        :user="user"
        @change-organization="$emit('change-organization', $event)"
        @manage-permissions="$emit('manage-permissions', $event)"
        @disable-user="$emit('disable-user', $event)"
        @reset-password="$emit('reset-password', $event)"
      />
    </div>

    <UserTableEmpty
      v-if="users.length === 0"
      :has-filters="hasFilters"
      @clear-filters="$emit('clear-filters')"
    />
  </div>
</template>

<script setup lang="ts">
import UserTableHeader from './UserTableHeader.vue'
import UserTableRow from './UserTableRow.vue'
import UserTableEmpty from './UserTableEmpty.vue'
import type { AdminUserResponse } from '@/types/admin-user'

interface Props {
  users: AdminUserResponse[]
  hasFilters: boolean
}

defineProps<Props>()

defineEmits<{
  'change-organization': [user: AdminUserResponse]
  'manage-permissions': [user: AdminUserResponse]
  'disable-user': [user: AdminUserResponse]
  'reset-password': [user: AdminUserResponse]
  'clear-filters': []
}>()
</script>
