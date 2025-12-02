<template>
  <div class="px-6 py-4 border-b border-primary-stroke bg-base-200">
    <div class="grid grid-cols-12 gap-4 text-sm font-medium text-secondary">
      <div class="col-span-2">{{ $t('admin.users.table.username', 'Username') }}</div>
      <div class="col-span-3">{{ $t('admin.users.table.email', 'Email') }}</div>
      <div class="col-span-2">{{ $t('admin.users.table.organization', 'Organization') }}</div>
      <div class="col-span-4">{{ $t('admin.users.table.permissions', 'Permissions') }}</div>
      <div class="col-span-1 text-right">{{ $t('admin.users.table.actions', 'Actions') }}</div>
    </div>
  </div>
</template>

<script setup lang="ts">
// Table header component for admin users table
</script>
