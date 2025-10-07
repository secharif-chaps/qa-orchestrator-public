<template>
  <div
    class="flex items-center justify-between p-4 border border-primary-stroke rounded-lg transition-all duration-200"
    :class="[
      value ? 'bg-primary/5 border-primary/20' : 'bg-base-100 hover:bg-base-200/50',
      disabled || (permission.key === 'workspace.write' && !canManageWorkspace) ? 'opacity-60' : '',
    ]"
  >
    <div class="flex-1">
      <div class="flex items-center gap-3">
        <div class="flex-shrink-0">
          <div
            class="w-10 h-10 rounded-lg flex items-center justify-center transition-colors"
            :class="value ? 'bg-primary/10' : 'bg-base-200'"
          >
            <i
              :class="[
                permission.icon,
                'text-lg transition-colors',
                value ? 'text-primary-content' : 'text-primary-light-content',
              ]"
            ></i>
          </div>
        </div>
        <div class="flex-1">
          <div class="font-medium text-base">{{ permission.name }}</div>
          <div class="text-sm text-primary-light-content">{{ permission.description }}</div>
        </div>
      </div>
    </div>

    <div class="flex items-center ml-4">
      <Switch.Root
        :id="`permission-${permission.key}`"
        v-model="value"
        :disabled="disabled || (permission.key === 'workspace.write' && !canManageWorkspace)"
        class="relative inline-flex h-6 w-11 items-center rounded-full transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
        :class="[value ? 'bg-primary' : 'bg-base-200 border border-primary-stroke']"
      >
        <Switch.Thumb
          class="inline-block h-4 w-4 transform rounded-full bg-white shadow-sm transition-transform duration-200"
          :class="value ? 'translate-x-6' : 'translate-x-1'"
        />
      </Switch.Root>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Switch } from 'reka-ui/namespaced'
import { ref, watch } from 'vue'

interface Permission {
  key: string
  name: string
  description: string
  icon: string
}

const props = defineProps<{
  permission: Permission
  isSelected: boolean
  disabled?: boolean
  canManageWorkspace?: boolean
}>()

const value = ref(props.isSelected)

const emit = defineEmits<{
  toggle: [permissionKey: string]
}>()

watch(value, () => {
  emit('toggle', props.permission.key)
})
</script>
