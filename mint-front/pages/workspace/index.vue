<template>
  <div class="container mx-auto py-8 px-4">
    <div class="mb-8">
      <h1 class="text-3xl font-bold mb-2">{{ $t('workspace.title') }}</h1>
      <p class="text-secondary">{{ $t('workspace.description') }}</p>
    </div>

    <div v-if="loading" class="flex items-center justify-center py-12">
      <i class="fas fa-spinner fa-spin text-3xl text-primary"></i>
    </div>

    <div v-else-if="error" class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-md">
      <p>{{ $t('workspace.error.loading') }}: {{ error.message }}</p>
    </div>

    <div v-else class="space-y-8">
      <div class="bg-bg1 rounded-lg shadow-sm border border-border-2">
        <div class="px-6 py-4 border-b border-border-2">
          <h2 class="text-xl font-semibold">{{ $t('workspace.info.title') }}</h2>
        </div>
        <div class="px-6 py-4">
          <dl class="space-y-4">
            <div>
              <dt class="text-sm font-medium text-secondary">{{ $t('workspace.info.name') }}</dt>
              <dd class="mt-1 text-sm">{{ workspace?.name }}</dd>
            </div>
            <div v-if="workspace?.description">
              <dt class="text-sm font-medium text-secondary">{{ $t('workspace.info.description') }}</dt>
              <dd class="mt-1 text-sm">{{ workspace.description }}</dd>
            </div>
            <div>
              <dt class="text-sm font-medium text-secondary">{{ $t('workspace.info.slug') }}</dt>
              <dd class="mt-1 text-sm font-mono text-primary">{{ workspace?.slug }}</dd>
            </div>
          </dl>
        </div>
      </div>

      <div class="bg-bg1 rounded-lg shadow-sm border border-border-2">
        <div class="px-6 py-4 border-b border-border-2 flex items-center justify-between">
          <h2 class="text-xl font-semibold">{{ $t('workspace.members.title') }}</h2>
          <OButton
            @click="showAddMemberDialog = true"
            size="sm"
            color="blue"
            icon="fas fa-plus"
          >
            {{ $t('workspace.members.add') }}
          </OButton>
        </div>
        <div class="px-6 py-4">
          <div v-if="members.length === 0" class="text-center py-8 text-secondary">
            <i class="fas fa-users text-4xl mb-4"></i>
            <p>{{ $t('workspace.members.empty') }}</p>
          </div>
          <div v-else class="space-y-4">
            <div
              v-for="member in members"
              :key="member.user_id"
              class="flex items-center justify-between py-3 px-4 bg-bg2 rounded-lg"
            >
              <div class="flex items-center space-x-4">
                <div class="w-10 h-10 bg-primary rounded-full flex items-center justify-center text-white font-semibold">
                  {{ member.username.charAt(0).toUpperCase() }}
                </div>
                <div>
                  <p class="font-medium">{{ member.username }}</p>
                  <p class="text-sm text-secondary">{{ member.email }}</p>
                </div>
              </div>
              <div class="flex items-center space-x-2">
                <OBadge
                  :color="member.status === 'active' ? 'green' : 'gray'"
                  size="sm"
                >
                  {{ $t(`workspace.members.status.${member.status}`) }}
                </OBadge>
                <OButton
                  v-if="member.user_id !== currentUserId"
                  @click="toggleMemberStatus(member)"
                  size="sm"
                  :color="member.status === 'active' ? 'rose' : 'green'"
                  variant="ghost"
                >
                  {{ member.status === 'active' ? $t('workspace.members.revoke') : $t('workspace.members.activate') }}
                </OButton>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <OModal :display-modal="showAddMemberDialog" v-model="showAddMemberDialog" :title="$t('workspace.members.addDialog.title')">
      <template #description>
        <div>
          <label class="block text-sm font-medium mb-1">{{ $t('workspace.members.addDialog.email') }}</label>
          <OInput
            id="email"
            v-model="newMember.email"
            type="email"
            :placeholder="$t('workspace.members.addDialog.emailPlaceholder')"
            required
          />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">{{ $t('workspace.members.addDialog.username') }}</label>
          <OInput
            id="username"
            v-model="newMember.username"
            :placeholder="$t('workspace.members.addDialog.usernamePlaceholder')"
          />
        </div>
      </template>
      <template #footer>
        <div class="flex justify-end space-x-2">
          <OButton
            @click="showAddMemberDialog = false"
            variant="ghost"
            color="gray"
          >
            {{ $t('common.cancel') }}
          </OButton>
          <OButton
            type="primary"
            color="blue"
            :loading="addingMember"
          >
            {{ $t('workspace.members.addDialog.submit') }}
          </OButton>
        </div>
      </template>
    </OModal>
  </div>


</template>

<script setup lang="ts">
import { OButton, OBadge, OModal, OInput } from '@owlint/feathers-vue'

interface WorkspaceMember {
  id: number
  workspace_id: number
  user_id: string
  username: string
  email: string
  status: 'active' | 'revoked'
  created_at: string
  updated_at: string
}

interface Workspace {
  id: number
  name: string
  description?: string
  slug: string
  created_at: string
  updated_at: string
}

const config = useRuntimeConfig()
const { getAccessToken, currentUser } = useAuth()
const { show } = useNotification()

const workspace = ref<Workspace | null>(null)
const members = ref<WorkspaceMember[]>([])
const loading = ref(true)
const error = ref<Error | null>(null)
const showAddMemberDialog = ref(false)
const addingMember = ref(false)
const newMember = reactive({
  email: '',
  username: ''
})

const currentUserId = computed(() => currentUser?.profile?.sub || '')

const fetchWorkspaceData = async () => {
  try {
    loading.value = true
    error.value = null
    
    const token = await getAccessToken()
    
    const [workspaceResponse, membersResponse] = await Promise.all([
      $fetch('/api/workspace/current', {
        baseURL: config.public.backendApi,
        headers: {
          Authorization: `Bearer ${token}`
        }
      }),
      $fetch('/api/workspace/current/members', {
        baseURL: config.public.backendApi,
        headers: {
          Authorization: `Bearer ${token}`
        }
      })
    ])
    
    workspace.value = workspaceResponse as Workspace
    members.value = membersResponse as WorkspaceMember[]
  } catch (err) {
    error.value = err as Error
    show({
      title: 'Error',
      message: 'Failed to load workspace data',
      type: 'error'
    })
  } finally {
    loading.value = false
  }
}

const addMember = async () => {
  try {
    addingMember.value = true
    const token = await getAccessToken()
    
    const response = await $fetch('/api/workspace/current/members', {
      method: 'POST',
      baseURL: config.public.backendApi,
      headers: {
        Authorization: `Bearer ${token}`
      },
      body: {
        email: newMember.email,
        username: newMember.username || undefined
      }
    })
    
    members.value.push(response as WorkspaceMember)
    showAddMemberDialog.value = false
    newMember.email = ''
    newMember.username = ''
    
    show({
      title: 'Success',
      message: 'Member added successfully',
      type: 'success'
    })
  } catch (err) {
    show({
      title: 'Error',
      message: 'Failed to add member',
      type: 'error'
    })
  } finally {
    addingMember.value = false
  }
}

const toggleMemberStatus = async (member: WorkspaceMember) => {
  try {
    const token = await getAccessToken()
    const newStatus = member.status === 'active' ? 'revoked' : 'active'
    
    const response = await $fetch(`/api/workspace/current/members/${member.user_id}`, {
      method: 'PUT',
      baseURL: config.public.backendApi,
      headers: {
        Authorization: `Bearer ${token}`
      },
      body: {
        status: newStatus
      }
    })
    
    const index = members.value.findIndex(m => m.user_id === member.user_id)
    if (index > -1) {
      members.value[index] = response as WorkspaceMember
    }
    
    show({
      title: 'Success',
      message: `Member ${newStatus === 'active' ? 'activated' : 'revoked'} successfully`,
      type: 'success'
    })
  } catch (err) {
    show({
      title: 'Error',
      message: 'Failed to update member status',
      type: 'error'
    })
  }
}

onMounted(() => {
  fetchWorkspaceData()
})
</script>