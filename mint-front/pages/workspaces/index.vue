<template>
  <div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
      <div>
        <h1 class="text-2xl font-bold">{{ $t('workspaces.title') }}</h1>
        <p class="text-secondary">{{ $t('workspaces.description') }}</p>
      </div>
      <OButton 
        :label="$t('workspaces.actions.create')"
        type="primary"
        color="blue"
        icon="fa fa-plus"
        @click="showCreateModal = true"
      />
    </div>

    <div v-if="status === 'pending'" class="flex justify-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
    </div>


    <div v-else-if="error" class="text-center py-8">
      <p class="text-red-600">{{ $t('workspaces.error.loading') }}</p>
    </div>

    <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
      <div 
        v-for="workspace in workspaces" 
        :key="workspace.id"
        class="border border-border-2 bg-bg1 rounded-lg p-6 hover:shadow-md transition-shadow"
      >
        <div class="flex justify-between items-start mb-4">
          <div>
            <h3 class="text-lg font-semibold">{{ workspace.name }}</h3>
            <p class="text-secondary text-sm">{{ workspace.slug }}</p>
          </div>
          <div class="flex space-x-2">
            <button 
              @click="editWorkspace(workspace)"
              class="text-blue-600 hover:text-blue-800"
            >
              <i class="fas fa-edit"></i>
            </button>
            <button 
              @click="deleteWorkspace(workspace)"
              class="text-red-600 hover:text-red-800"
            >
              <i class="fas fa-trash"></i>
            </button>
          </div>
        </div>
        
        <p class="text-sm mb-4">{{ workspace.description }}</p>
        
        <div class="flex justify-between items-center text-sm text-secondary">
          <span>{{ $t('workspaces.fields.created') }}: {{ formatDate(workspace.created_at) }}</span>
          <span>{{ workspace.member_count || 0 }} {{ $t('workspaces.fields.members') }}</span>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <OModal 
      v-model="showCreateModal"
      :title="editingWorkspace ? $t('workspaces.modal.edit') : $t('workspaces.modal.create')"
    >
      <form @submit.prevent="saveWorkspace" class="space-y-4">
        <OInput
          id="name"
          v-model="workspaceForm.name"
          :label="$t('workspaces.fields.name')"
          :placeholder="$t('workspaces.placeholders.name')"
          required
        />
        
        <OInput
          id="slug"
          v-model="workspaceForm.slug"
          :label="$t('workspaces.fields.slug')"
          :placeholder="$t('workspaces.placeholders.slug')"
          required
        />
        
        <div>
          <label class="block text-sm font-medium mb-2">{{ $t('workspaces.fields.description') }}</label>
          <textarea
            v-model="workspaceForm.description"
            :placeholder="$t('workspaces.placeholders.description')"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            rows="3"
          />
        </div>
        
        <div class="flex justify-end space-x-2 pt-4">
          <OButton
            :label="$t('common.cancel')"
            type="secondary"
            @click="showCreateModal = false"
          />
          <OButton
            :label="editingWorkspace ? $t('common.update') : $t('common.create')"
            type="primary"
            color="blue"
            :loading="saving"
            @click="saveWorkspace"
          />
        </div>
      </form>
    </OModal>
  </div>
</template>

<script setup lang="ts">
import { OButton, OInput, OModal } from '@owlint/feathers-vue'
import { useI18n } from 'vue-i18n'


const { t } = useI18n()

// State
const showCreateModal = ref(false)
const editingWorkspace = ref(null)
const saving = ref(false)

const workspaceForm = reactive({
  name: '',
  slug: '',
  description: ''
})

const workspaceRepository = useWorkspace()

// Fetch workspaces data
const { workspaces, status, error, refresh } = await workspaceRepository.fetchWorkspaces()

// Methods
const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString()
}

const editWorkspace = (workspace: any) => {
  editingWorkspace.value = workspace
  workspaceForm.name = workspace.name
  workspaceForm.slug = workspace.slug
  workspaceForm.description = workspace.description || ''
  showCreateModal.value = true
}

const deleteWorkspace = async (workspace: any) => {
  if (confirm(t('workspaces.confirm.delete', { name: workspace.name }))) {
    try {
      await workspaceRepository.deleteWorkspace(workspace.id)
      await refresh()
    } catch (error) {
      console.error('Error deleting workspace:', error)
      alert(t('workspaces.error.delete'))
    }
  }
}

const saveWorkspace = async () => {
  saving.value = true
  try {
    if (editingWorkspace.value) {
      await workspaceRepository.updateWorkspace(editingWorkspace.value.id, workspaceForm)
    } else {
      await workspaceRepository.createWorkspace(workspaceForm)
    }
    
    showCreateModal.value = false
    editingWorkspace.value = null
    Object.assign(workspaceForm, { name: '', slug: '', description: '' })
    await refresh()
  } catch (error) {
    console.error('Error saving workspace:', error)
    alert(t('workspaces.error.save'))
  } finally {
    saving.value = false
  }
}

// Reset form when modal closes
watch(showCreateModal, (isOpen) => {
  if (!isOpen) {
    editingWorkspace.value = null
    Object.assign(workspaceForm, { name: '', slug: '', description: '' })
  }
})
</script>