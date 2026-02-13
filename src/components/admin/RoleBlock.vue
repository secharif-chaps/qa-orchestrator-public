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
        <Icon :icon="role.icon ?? 'fa-shield-check'" :class="isAdmin ? 'text-error' : 'text-primary'" />
      </div>

      <div class="flex-1">
        <h3 :class="['font-semibold text-base', isAdmin ? 'text-error' : '']">{{ $t(`admin.permissions.roles.${role.id}.name`) }}</h3>
        <p class="text-sm text-secondary">{{ $t(`admin.permissions.roles.${role.id}.description`) }}</p>
      </div>

      <Icon v-if="selected" icon="fa-check-circle" class="size-6 flex-shrink-0" :class="isAdmin ? 'text-error' : 'text-primary'"/>

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
import { Icon } from '@owlint/feathers-vue'
import Tag from '@/components/ui/Tag.vue'

interface Props {
  role: Role
  selected: boolean
}

interface Emits {
  select: [roleId: string]
}

const { role, selected } = defineProps<Props>()
defineEmits<Emits>()

const isAdmin = computed(() => role.id === 'admin')

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
