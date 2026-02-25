<template>
  <div
    class="flex flex-col gap-3 rounded-lg border p-4 transition-all"
    :class="blockClasses"
    :title="disabled ? disabledReason : undefined"
    @click="disabled ? null : $emit('select', role.id)"
  >
    <!-- Header -->
    <div class="flex items-center gap-3">
      <div
        class="flex size-10 items-center justify-center rounded-lg"
        :class="isAdmin ? 'bg-error-light' : 'bg-primary/10'"
      >
        <Icon
          :icon="role.icon ?? 'fa-shield-check'"
          :class="isAdmin ? 'text-error' : 'text-primary'"
        />
      </div>

      <div class="flex-1">
        <h3 :class="['text-base font-semibold', isAdmin ? 'text-error' : '']">
          {{ $t(`admin.permissions.roles.${role.id}.name`) }}
        </h3>
        <p class="text-secondary text-sm">
          {{ $t(`admin.permissions.roles.${role.id}.description`) }}
        </p>
      </div>

      <Icon
        v-if="selected"
        icon="fa-check-circle"
        class="size-6 flex-shrink-0"
        :class="isAdmin ? 'text-error' : 'text-primary'"
      />
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
  disabled?: boolean
  disabledReason?: string
}

interface Emits {
  select: [roleId: string]
}

const { role, selected, disabled } = defineProps<Props>()
defineEmits<Emits>()

const isAdmin = computed(() => role.id === 'admin')

const blockClasses = computed(() => {
  // Disabled state takes priority
  if (disabled) {
    return 'opacity-50 cursor-not-allowed border-gray-200'
  }

  if (isAdmin.value) {
    return selected
      ? 'border-error bg-error/5 cursor-pointer'
      : 'border-error-stroke hover:border-error/30 hover:bg-base-200 cursor-pointer'
  }
  return selected
    ? 'border-primary bg-primary/5 cursor-pointer'
    : 'border-primary-stroke hover:border-primary/30 hover:bg-base-200 cursor-pointer'
})
</script>
