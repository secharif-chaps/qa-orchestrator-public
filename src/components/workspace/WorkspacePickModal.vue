<template>
  <div
    class="fixed inset-0 bg-bg1/20 backdrop-blur-sm flex items-center justify-center z-50"
    @click.self="$emit('cancel')"
  >
    <div
      class="bg-gradient-to-br from-bg1 to-bg2 rounded-xl shadow-2xl border border-border-2 p-6 max-w-xl w-full mx-4"
    >
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <h3 class="text-lg font-semibold text-base">
          {{ $t('workspace.pick.title', 'Switch Workspace') }}
        </h3>
        <Button variant="tertiary" icon="fa fa-times" icon-only @click="$emit('cancel')" />
      </div>

      <!-- Content -->
      <div class="mb-6">
        <p class="text-secondary mb-4">
          {{
            $t('workspace.pick.confirmation', 'Are you sure you want to switch to this workspace?')
          }}
        </p>

        <div class="bg-gradient-to-r from-bg2 to-bg1 p-4 rounded-lg border border-border-2">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center">
              <i class="fa fa-building text-primary"></i>
            </div>
            <div>
              <h4 class="font-medium text-base">{{ workspace.name }}</h4>
              <p class="text-sm text-secondary">{{ workspace.slug }}</p>
              <p v-if="workspace.description" class="text-sm text-secondary mt-1">
                {{ workspace.description }}
              </p>
            </div>
          </div>
        </div>

        <Alert
          variant="warning"
          :title="$t('workspace.pick.notice.title', 'Admin Feature')"
          :message="
            $t(
              'workspace.pick.notice.description',
              'This action will switch your current workspace and refresh the page to update all workspace-specific data.',
            )
          "
          icon="fa fa-info-circle"
          decoration-icon="fa fa-exclamation-triangle"
          :dismissible="false"
          class="mt-4"
        />
      </div>

      <!-- Actions -->
      <div class="flex items-center gap-3 justify-end">
        <Button
          variant="tertiary"
          :label="$t('common.cancel', 'Cancel')"
          @click="$emit('cancel')"
        />

        <Button
          variant="primary"
          icon="fa fa-exchange-alt"
          :label="
            isLoading
              ? $t('workspace.pick.switching', 'Switching...')
              : $t('workspace.pick.switch', 'Switch Workspace')
          "
          :loading="isLoading"
          :disabled="isLoading"
          @click="$emit('confirm', workspace.id)"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { WorkspaceResponse } from '@/types/workspace'
import Alert from '@/components/ui/Alert.vue'
import Button from '@/components/ui/Button.vue'

interface Props {
  workspace: WorkspaceResponse
  isLoading?: boolean
}

defineProps<Props>()

defineEmits<{
  confirm: [workspaceId: number]
  cancel: []
}>()
</script>
