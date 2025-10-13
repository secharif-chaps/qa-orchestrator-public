<template>
  <div>
    <!-- Header -->
    <div class="mb-8">
      <button
        @click="$router.push('/workspaces')"
        class="text-secondary hover:text-base transition-colors p-2 flex items-center gap-2"
      >
        <i class="fa fa-arrow-left"></i>
        <span>Back</span>
      </button>
    </div>

    <!-- Create Form -->
    <div class="max-w-2xl mx-auto">
      <div class="flex items-center gap-4 mb-4">
        <div>
          <h1 class="text-3xl font-bold text-base">
            {{ $t('workspace.create.title', 'Create Workspace') }}
          </h1>
          <p class="text-secondary mt-2">
            {{ $t('workspace.create.description', 'Create a new workspace for your organization') }}
          </p>
        </div>
      </div>
      <!-- Error Alert -->
      <div
        v-if="error"
        class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg"
      >
        <div class="flex items-center gap-2">
          <i class="fa fa-exclamation-triangle"></i>
          <span class="font-medium">Error:</span>
          <span>{{ error.message }}</span>
        </div>
      </div>

      <!-- Form Card -->
      <div class="bg-base-100 rounded-lg shadow-sm p-6">
        <form @submit.prevent="handleSubmit" class="space-y-6">
          <!-- Workspace Name -->
          <div>
            <label for="workspace-name" class="block text-sm font-medium text-base mb-2">
              {{ $t('workspace.form.name.label', 'Workspace Name') }}
              <span class="text-red-500">*</span>
            </label>
            <input
              id="workspace-name"
              v-model="name"
              @input="generateSlug"
              type="text"
              :placeholder="$t('workspace.form.name.placeholder', 'Enter workspace name...')"
              class="w-full px-3 py-2 border border-primary-stroke rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary bg-base-300"
              required
            />
            <p class="text-xs text-secondary mt-1">
              {{
                $t('workspace.form.name.help', 'This will be the display name for your workspace')
              }}
            </p>
          </div>

          <!-- Workspace Slug -->
          <div>
            <label for="workspace-slug" class="block text-sm font-medium text-base mb-2">
              {{ $t('workspace.form.slug.label', 'Workspace Slug') }}
            </label>
            <div class="flex items-center gap-2">
              <div
                class="flex-1 h-9 px-3 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary bg-base-200 font-mono text-sm"
              >
                {{ slug }}
              </div>
            </div>
            <p class="text-xs text-secondary mt-1">
              {{ $t('workspace.form.slug.help', 'URL-friendly identifier (lowercase, no spaces)') }}
            </p>
          </div>

          <!-- Workspace Description -->
          <div>
            <label for="workspace-description" class="block text-sm font-medium text-base mb-2">
              {{ $t('workspace.form.description.label', 'Description') }}
              <span class="text-secondary text-sm font-normal"
                >({{ $t('common.optional', 'optional') }})</span
              >
            </label>
            <textarea
              id="workspace-description"
              v-model="description"
              rows="3"
              :placeholder="
                $t(
                  'workspace.form.description.placeholder',
                  'Describe the purpose of this workspace...',
                )
              "
              class="w-full px-3 py-2 border border-primary-stroke rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary bg-base-300 resize-none"
            ></textarea>
            <p class="text-xs text-secondary mt-1">
              {{
                $t(
                  'workspace.form.description.help',
                  'Brief description to help users understand this workspace',
                )
              }}
            </p>
          </div>

          <!-- Form Actions -->
          <div class="flex items-center justify-end gap-4 pt-4 border-t border-primary-stroke">
            <button
              type="button"
              @click="$router.push('/workspaces')"
              class="px-6 py-2 text-secondary hover:text-base transition-colors"
            >
              {{ $t('common.cancel', 'Cancel') }}
            </button>
            <button
              type="submit"
              :disabled="!isFormValid || isLoading"
              class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary/80 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
            >
              <div
                v-if="isLoading"
                class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"
              ></div>
              <i v-else class="fa fa-plus"></i>
              {{ $t('workspace.create.submit', 'Create Workspace') }}
            </button>
          </div>
        </form>
      </div>

      <!-- Preview Card -->
      <div v-if="name || description" class="mt-6 bg-base-100 rounded-lg shadow-sm p-6">
        <h3 class="text-lg font-medium text-base mb-4">
          {{ $t('workspace.create.preview', 'Preview') }}
        </h3>
        <div class="border border-primary-stroke rounded-lg p-4">
          <div class="flex items-start justify-between">
            <div class="flex-1">
              <h4 class="font-medium text-base">
                {{ name || $t('workspace.form.name.placeholder', 'Enter workspace name...') }}
              </h4>
              <p v-if="description" class="text-secondary text-sm mt-1">
                {{ description }}
              </p>
              <div class="flex items-center gap-4 mt-3 text-xs text-secondary">
                <span v-if="slug">
                  <i class="fa fa-link mr-1"></i>
                  {{ slug }}
                </span>
                <span>
                  <i class="fa fa-users mr-1"></i>
                  0 {{ $t('workspace.members', 'members') }}
                </span>
                <span>
                  <i class="fa fa-calendar mr-1"></i>
                  {{ $t('workspace.justCreated', 'Just created') }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - admin.workspaces
</route>

<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useCreateWorkspace } from '@/mutations/workspace'
import { generateSlug as generateSlugUtil } from '@/utils/slug'

const router = useRouter()

// Mutation
const { name, description, slug, createWorkspace, isLoading, error } = useCreateWorkspace()

// Form validation
const isFormValid = computed(() => {
  return name.value.trim().length > 0 && slug.value.trim().length > 0
})

// Generate slug from name
const generateSlug = () => {
  if (name.value.trim()) {
    slug.value = generateSlugUtil(name.value)
  }
}

// Handle form submission
const handleSubmit = async () => {
  try {
    await createWorkspace()
    // Redirect to workspaces list on success
    router.push('/admin/workspaces')
  } catch (err) {
    console.error('Failed to create workspace:', err)
    // Error is handled by the mutation
  }
}
</script>
