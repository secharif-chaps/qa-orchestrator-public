<template>
  <div
    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
    @click.self="$emit('cancel')"
  >
    <div class="bg-bg1 rounded-lg shadow-lg p-6 max-w-md w-full mx-4">
      <!-- Header -->
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold">
          {{ $t('workspace.pick.title', 'Switch Workspace') }}
        </h3>
        <button
          @click="$emit('cancel')"
          class="text-secondary hover:text-base transition-colors p-1"
        >
          <i class="fa fa-times"></i>
        </button>
      </div>

      <!-- Content -->
      <div class="mb-6">
        <p class="text-secondary mb-4">
          {{
            $t('workspace.pick.confirmation', 'Are you sure you want to switch to this workspace?')
          }}
        </p>

        <div class="bg-bg2 p-4 rounded-lg border">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center">
              <i class="fa fa-building text-primary"></i>
            </div>
            <div>
              <h4 class="font-medium">{{ workspace.name }}</h4>
              <p class="text-sm text-secondary">{{ workspace.slug }}</p>
              <p v-if="workspace.description" class="text-sm text-secondary mt-1">
                {{ workspace.description }}
              </p>
            </div>
          </div>
        </div>

        <div class="mt-4 p-3 bg-yellow-400/10 border border-yellow-400 rounded-lg">
          <div class="flex items-start gap-2">
            <i class="fa fa-info-circle text-yellow-400 text-sm mt-0.5"></i>
            <div class="text-sm text-yellow-400">
              <p class="font-medium">{{ $t('workspace.pick.notice.title', 'Admin Feature') }}</p>
              <p class="mt-1">
                {{
                  $t(
                    'workspace.pick.notice.description',
                    'This action will switch your current workspace and refresh the page to update all workspace-specific data.',
                  )
                }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex items-center gap-3 justify-end">
        <button
          @click="$emit('cancel')"
          class="px-4 py-2 text-secondary hover:text-base transition-colors"
        >
          {{ $t('common.cancel', 'Cancel') }}
        </button>

        <button
          @click="$emit('confirm', workspace.id)"
          :disabled="isLoading"
          class="bg-primary text-white px-6 py-2 rounded-lg hover:bg-primary/80 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
        >
          <div
            v-if="isLoading"
            class="animate-spin rounded-full h-4 w-4 border-b-2 border-white"
          ></div>
          <i v-else class="fa fa-exchange-alt"></i>
          {{
            isLoading
              ? $t('workspace.pick.switching', 'Switching...')
              : $t('workspace.pick.switch', 'Switch Workspace')
          }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { WorkspaceResponse } from '@/types/workspace'

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
