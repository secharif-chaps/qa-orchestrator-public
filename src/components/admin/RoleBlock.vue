<template>
  <div
    class="flex flex-col gap-3 rounded-lg border p-4 transition-all cursor-pointer"
    :class="blockClasses"
    @click="$emit('select', role.id)"
  >
    <!-- Header -->
    <div class="flex items-center gap-3">
      <div
        class="flex size-10 items-center justify-center rounded-lg" :class="isAdmin ? 'bg-error-light' : 'bg-primary/10'"
      >
        <i :class="[`fa ${role.icon}` || 'fas fa-shield-check', isAdmin ? 'text-error' : 'text-primary']"></i>
      </div>

      <div class="flex-1">
        <h3 :class="['font-semibold text-base', isAdmin ? 'text-error' : '']">{{ $t(`admin.permissions.roles.${role.id}.name`) }}</h3>
        <p class="text-sm text-secondary">{{ $t(`admin.permissions.roles.${role.id}.description`) }}</p>
      </div>

      <i
        v-if="selected" class="fa fa-circle-check size-6 flex-shrink-0"
        :class="isAdmin ? 'text-error' : 'text-primary'"
      ></i>
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
import { computed } from 'vue'
import type { Role } from '@/types/role'
import Tag from '@/components/ui/Tag.vue'

const { role, selected } = defineProps<{
  role: Role
  selected: boolean
}>()

defineEmits<{
  select: [roleId: string]
}>()

const isAdmin = computed(() => role.color === 'error')

const blockClasses = computed(() => {
  if (isAdmin.value) {
    return selected
      ? 'border-error bg-error/5'
      : 'border-error-stroke hover:border-error/30 hover:bg-base-200'
  }
  return selected
    ? 'border-primary bg-primary/5'
    : 'border-primary-stroke hover:border-primary/30 hover:bg-base-200'
})
</script>
