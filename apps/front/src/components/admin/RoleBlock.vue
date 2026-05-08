<template>
  <div
    class="flex flex-col gap-3 rounded-sm border p-4 transition-all"
    :class="blockClasses"
    :title="disabled ? disabledReason : undefined"
    @click="disabled ? null : $emit('select', role.id)"
  >
    <!-- Header -->
    <div class="flex items-center gap-3">
      <div
        class="flex size-10 items-center justify-center rounded-sm"
        :class="isAdmin ? 'bg-error-light' : 'bg-primary/10'"
      >
        <Icon
          :icon="role.icon ?? 'fa-shield-check'"
          :class="isAdmin ? 'text-error' : 'text-primary'"
        />
      </div>

      <div class="flex-1">
        <h3 :class="['text-base font-semibold', isAdmin ? 'text-error' : '']">
          {{ roleName }}
        </h3>
        <p class="text-neutral-black-font text-sm">
          {{ roleDescription }}
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
        intent="neutral"
        class="cursor-pointer"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import type { Role, RoleId } from '@/types/role'
import { Icon, Tag } from '@owlint/feathers-vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

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

const { t } = useI18n()

const isAdmin = computed(() => role.id === 'admin')

const roleNameMap: Record<RoleId, string> = {
  reader: t('admin.permissions.roles.reader.name'),
  writer: t('admin.permissions.roles.writer.name'),
  manager: t('admin.permissions.roles.manager.name'),
  admin: t('admin.permissions.roles.admin.name'),
}

const roleDescriptionMap: Record<RoleId, string> = {
  reader: t('admin.permissions.roles.reader.description'),
  writer: t('admin.permissions.roles.writer.description'),
  manager: t('admin.permissions.roles.manager.description'),
  admin: t('admin.permissions.roles.admin.description'),
}

const roleName = computed(() => roleNameMap[role.id as RoleId] ?? role.name)
const roleDescription = computed(() => roleDescriptionMap[role.id as RoleId] ?? role.description)

const blockClasses = computed(() => {
  // Disabled state takes priority
  if (disabled) {
    return 'opacity-50 cursor-not-allowed border-gray-200'
  }

  if (isAdmin.value) {
    return selected
      ? 'border-error bg-error/5 cursor-pointer'
      : 'border-error-stroke hover:border-error/30 hover:bg-primary-lightest cursor-pointer'
  }
  return selected
    ? 'border-primary bg-primary/5 cursor-pointer'
    : 'border-primary-lighter-stroke hover:border-primary/30 hover:bg-primary-lightest cursor-pointer'
})
</script>
