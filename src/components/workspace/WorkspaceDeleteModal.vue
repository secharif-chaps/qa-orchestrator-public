<template>
  <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
    <div class="bg-bg1 rounded-lg shadow-xl max-w-md w-full mx-4">
      <!-- Header -->
      <div class="p-6 border-b border-border-2">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
            <i class="fa fa-exclamation-triangle text-red-600"></i>
          </div>
          <div>
            <h3 class="text-lg font-semibold text-base">
              {{ $t('workspace.delete.title', 'Delete Workspace') }}
            </h3>
            <p class="text-sm text-secondary">
              {{ $t('workspace.delete.subtitle', 'This action cannot be undone') }}
            </p>
          </div>
        </div>
      </div>

      <!-- Content -->
      <div class="p-6">
        <!-- Warning Message -->
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
          <div class="flex items-start gap-3">
            <i class="fa fa-exclamation-triangle text-red-500 mt-0.5"></i>
            <div class="text-red-700 text-sm leading-relaxed">
              <p class="font-medium mb-2">
                {{ $t('workspace.delete.warning.title', 'Warning: Users will lose access') }}
              </p>
              <p>
                {{
                  $t(
                    'workspace.delete.warning.message',
                    'All users linked to this workspace will no longer be able to access the application. Make sure to migrate users to another workspace before deleting.',
                  )
                }}
              </p>
            </div>
          </div>
        </div>

        <!-- Workspace Details -->
        <div class="mb-6 bg-bg2 rounded-lg p-4">
          <h4 class="font-medium text-base mb-3">
            {{ $t('workspace.delete.details', 'Workspace Details') }}
          </h4>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-secondary">{{ $t('workspace.name', 'Name') }}:</span>
              <span class="font-medium">{{ workspace.name }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-secondary">{{ $t('workspace.slug', 'Slug') }}:</span>
              <code class="text-xs bg-bg3 px-2 py-1 rounded">{{ workspace.slug }}</code>
            </div>
            <div v-if="workspace.description" class="flex justify-between">
              <span class="text-secondary">{{ $t('workspace.description', 'Description') }}:</span>
              <span class="max-w-48 truncate">{{ workspace.description }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-secondary">{{ $t('workspace.created', 'Created') }}:</span>
              <span>{{ formatDate(workspace.created_at) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-secondary">{{ $t('workspace.members', 'Members') }}:</span>
              <span class="inline-flex items-center gap-1">
                <i class="fa fa-users text-primary text-xs"></i>
                {{ memberCount }} {{ $t('workspace.users', 'users') }}
              </span>
            </div>
          </div>
        </div>

        <!-- Confirmation Input -->
        <div class="mb-6">
          <p class="text-sm text-secondary mb-3">
            {{
              $t('workspace.delete.confirm.message', 'Type the workspace name to confirm deletion:')
            }}
          </p>
          <div class="space-y-2">
            <code class="text-sm bg-bg3 px-2 py-1 rounded block">{{ workspace.slug }}</code>
            <input
              v-model="confirmationText"
              type="text"
              :placeholder="$t('workspace.delete.confirm.placeholder', 'Enter workspace name...')"
              class="w-full px-3 py-2 border border-border-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary bg-bg3"
            />
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="p-6 border-t border-border-2 flex items-center justify-end gap-3">
        <Button
          variant="ghost-primary"
          :label="$t('common.cancel', 'Cancel')"
          @click="$emit('cancel')"
        />
        <Button
          variant="primary"
          color="danger"
          icon="fa fa-trash"
          :label="$t('workspace.delete.confirm.button', 'Delete Workspace')"
          :loading="isLoading"
          :disabled="!isConfirmed || isLoading"
          @click="$emit('confirm', workspace.id)"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import type { WorkspaceResponse } from '@/types/workspace'
import Button from '@/components/ui/Button.vue'

interface Props {
  workspace: WorkspaceResponse
  isLoading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  isLoading: false,
})

const emit = defineEmits<{
  confirm: [id: number]
  cancel: []
}>()

const confirmationText = ref('')

// Hardcoded member count for now
const memberCount = computed(() => {
  return props.workspace.id === 1 ? 15 : Math.floor(Math.random() * 10) + 1
})

const isConfirmed = computed(() => {
  return confirmationText.value.trim() === props.workspace.slug.trim()
})

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}
</script>
