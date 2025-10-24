<template>
  <div
    class="fixed inset-0 bg-base-100/20 backdrop-blur-sm flex items-center justify-center z-50"
    @click.self="$emit('cancel')"
  >
    <div
      class="bg-base-100 rounded-xl shadow-2xl border border-primary-stroke p-6 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto"
    >
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <h3 class="text-lg font-semibold text-base">
          {{
            user.workspace_name
              ? $t('admin.users.modal.changeWorkspace', 'Change User Workspace')
              : $t('admin.users.modal.assignWorkspace', 'Assign User to Workspace')
          }}
        </h3>
        <Button variant="tertiary" icon="fa fa-times" icon-only @click="$emit('cancel')" />
      </div>

      <!-- User Info -->
      <div class="mb-6 bg-base-200 p-4 rounded-lg border border-primary-stroke">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-primary/10 rounded-full flex items-center justify-center">
            <i class="fa fa-user text-secondary"></i>
          </div>
          <div>
            <div class="font-medium text-base">{{ user.username }}</div>
            <div class="text-sm text-secondary">{{ user.email }}</div>
          </div>
        </div>

        <!-- Current Workspace -->
        <div v-if="user.workspace_name" class="mt-3 pt-3 border-t border-primary-stroke">
          <div class="text-xs text-secondary mb-1">Current workspace:</div>
          <div class="flex items-center gap-2">
            <span
              class="text-sm bg-primary-light text-primary-light-content border border-primary-stroke px-2 py-1 rounded"
            >
              {{ user.workspace_name }}
            </span>
          </div>
        </div>
        <div v-else class="mt-3 pt-3 border-t border-primary-stroke">
          <div class="text-xs text-secondary italic">No workspace assigned</div>
        </div>
      </div>

      <!-- Workspace Selection -->
      <div class="mb-6">
        <h4 class="text-sm font-medium text-secondary mb-3">
          {{ $t('admin.users.modal.selectWorkspace', 'Select workspace:') }}
        </h4>

        <div class="space-y-2 max-h-96 overflow-y-auto">
          <button
            v-for="workspace in workspaces"
            :key="workspace.id"
            @click="selectedWorkspaceId = workspace.id"
            class="w-full text-left p-3 rounded-lg border transition-colors"
            :class="{
              'border-primary bg-primary/5': selectedWorkspaceId === workspace.id,
              'border-primary-stroke hover:bg-base-200': selectedWorkspaceId !== workspace.id,
              'opacity-50': workspace.id === user.workspace_id,
            }"
            :disabled="workspace.id === user.workspace_id"
          >
            <div class="flex items-center justify-between">
              <div class="flex-1">
                <div class="flex items-center gap-2">
                  <i
                    class="fa fa-building text-sm"
                    :class="{
                      'text-primary': selectedWorkspaceId === workspace.id,
                      'text-secondary': selectedWorkspaceId !== workspace.id,
                    }"
                  ></i>
                  <span class="font-medium">{{ workspace.name }}</span>
                  <span
                    v-if="workspace.id === user.workspace_id"
                    class="text-xs text-secondary"
                  >
                    (current)
                  </span>
                </div>
                <div v-if="workspace.description" class="text-sm text-secondary mt-1">
                  {{ workspace.description }}
                </div>
              </div>
              <div v-if="selectedWorkspaceId === workspace.id">
                <i class="fa fa-check-circle text-primary"></i>
              </div>
            </div>
          </button>

          <!-- Empty state -->
          <div v-if="workspaces.length === 0" class="text-center py-8">
            <i class="fa fa-building text-4xl text-secondary/50 mb-2"></i>
            <p class="text-sm text-secondary">No workspaces available</p>
          </div>
        </div>
      </div>

      <!-- Info Alert -->
      <Alert
        v-if="user.workspace_name && selectedWorkspaceId !== user.workspace_id"
        variant="warning"
        :title="$t('admin.users.modal.warning.title', 'Workspace Change')"
        :message="
          $t(
            'admin.users.modal.warning.message',
            'Changing this user\'s workspace will move them to the new workspace. Their data will remain in the original workspace.',
          )
        "
        icon="fa fa-info-circle"
        :dismissible="false"
        class="mb-4"
      />

      <!-- Actions -->
      <div class="flex items-center gap-3 justify-end">
        <Button
          variant="tertiary"
          :label="$t('common.cancel', 'Cancel')"
          @click="$emit('cancel')"
          :disabled="isLoading"
        />

        <Button
          variant="primary"
          icon="fa fa-check"
          :label="
            isLoading
              ? $t('admin.users.modal.assigning', 'Assigning...')
              : user.workspace_name
                ? $t('admin.users.modal.changeWorkspace', 'Change Workspace')
                : $t('admin.users.modal.assignWorkspace', 'Assign Workspace')
          "
          :loading="isLoading"
          :disabled="isLoading || selectedWorkspaceId === null || selectedWorkspaceId === user.workspace_id"
          @click="handleConfirm"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import Alert from '@/components/ui/Alert.vue'
import Button from '@/components/ui/Button.vue'
import type { AdminUserResponse } from '@/types/admin-user'
import type { WorkspaceResponse } from '@/types/workspace'

interface Props {
  user: AdminUserResponse
  workspaces: WorkspaceResponse[]
  isLoading?: boolean
}

const props = defineProps<Props>()

const emit = defineEmits<{
  confirm: [workspaceId: number]
  cancel: []
}>()

// Selected workspace ID
const selectedWorkspaceId = ref<number | null>(props.user.workspace_id)

// Handle confirm
const handleConfirm = () => {
  if (selectedWorkspaceId.value !== null) {
    emit('confirm', selectedWorkspaceId.value)
  }
}
</script>
