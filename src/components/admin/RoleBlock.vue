<template>
  <div
    class="flex flex-col gap-3 rounded-lg border p-4 transition-all cursor-pointer"
    :class="{
      'border-primary bg-primary/5': selected,
      'border-primary-stroke hover:border-primary/30 hover:bg-base-200': !selected,
    }"
    @click="$emit('select', role.id)"
  >
    <!-- Header -->
    <div class="flex items-center gap-3">
      <div class="flex size-10 items-center justify-center rounded-lg bg-primary/10">
        <i :class="[`fa ${role.icon}` || 'fas fa-shield-check', 'text-primary']"></i>
      </div>

      <div class="flex-1">
        <h3 class="font-semibold text-base">{{ $t(`admin.permissions.roles.${role.id}.name`) }}</h3>
        <p class="text-sm text-secondary">{{ $t(`admin.permissions.roles.${role.id}.description`) }}</p>
      </div>

      <i v-if="selected" class="fa fa-circle-check text-primary size-6 flex-shrink-0"></i>
    </div>

    <!-- Permissions -->
    <div class="flex flex-wrap gap-2">
      <Tag
        v-for="permission in role.permissions"
        :key="permission"
        :label="permission"
        size="sm"
        variant="slate"
        class="cursor-pointer"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import type { Role } from '@/types/role'
import Tag from '@/components/ui/Tag.vue'

defineProps<{
  role: Role
  selected: boolean
}>()

defineEmits<{
  select: [roleId: string]
}>()
</script>
