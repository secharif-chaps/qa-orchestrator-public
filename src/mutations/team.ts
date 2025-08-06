import { ref } from 'vue'
import { defineMutation, useMutation, useQueryCache } from '@pinia/colada'
import { 
  createWorkspaceUser, 
  updateWorkspaceUser, 
  disableWorkspaceUser, 
  enableWorkspaceUser 
} from '@/api/team'
import type { CreateWorkspaceUserRequest, UpdateWorkspaceUserRequest } from '@/types/team'
import { TEAM_QUERY_KEYS } from '@/queries/team'
import { toast } from '@/utils/toast'

export const useCreateWorkspaceUser = defineMutation(() => {
  const email = ref('')
  const username = ref('')
  const password = ref('')
  const firstName = ref('')
  const lastName = ref('')
  const permissions = ref<string[]>([])

  const queryCache = useQueryCache()

  const { mutate, ...mutation } = useMutation({
    mutation: (user: CreateWorkspaceUserRequest) => createWorkspaceUser(user),
    onSuccess: (newUser) => {
      toast.success(`User "${newUser.first_name} ${newUser.last_name}" created successfully!`)

      queryCache.invalidateQueries({ key: TEAM_QUERY_KEYS.users })

      email.value = ''
      username.value = ''
      password.value = ''
      firstName.value = ''
      lastName.value = ''
      permissions.value = []
    },
    onError: (error: any) => {
      const errorMessage = error?.message || 'Failed to create user'
      toast.error(errorMessage)
    },
  })

  const createUserWithForm = () => {
    if (!email.value.trim() || !username.value.trim() || !password.value.trim() || !firstName.value.trim() || !lastName.value.trim()) {
      throw new Error('All fields are required')
    }

    return mutate({
      email: email.value.trim(),
      username: username.value.trim(),
      password: password.value.trim(),
      first_name: firstName.value.trim(),
      last_name: lastName.value.trim(),
      permissions: permissions.value,
    })
  }

  return {
    ...mutation,
    email,
    username,
    password,
    firstName,
    lastName,
    permissions,
    createUser: createUserWithForm,
    mutate,
  }
})

export const useUpdateWorkspaceUser = defineMutation(() => {
  const queryCache = useQueryCache()

  const { mutate, ...mutation } = useMutation({
    mutation: ({ userId, updates }: { userId: number; updates: UpdateWorkspaceUserRequest }) =>
      updateWorkspaceUser(userId, updates),
    onSuccess: (updatedUser, { userId }) => {
      toast.success(`User "${updatedUser.first_name} ${updatedUser.last_name}" updated successfully!`)

      queryCache.invalidateQueries({ key: TEAM_QUERY_KEYS.user(userId) })
      queryCache.invalidateQueries({ key: TEAM_QUERY_KEYS.users })
    },
    onError: (error: any) => {
      const errorMessage = error?.message || 'Failed to update user'
      toast.error(errorMessage)
    },
  })

  return {
    ...mutation,
    updateUser: mutate,
  }
})

export const useToggleWorkspaceUser = defineMutation(() => {
  const queryCache = useQueryCache()

  const { mutate: disable, ...disableMutation } = useMutation({
    mutation: (userId: number) => disableWorkspaceUser(userId),
    onSuccess: (updatedUser) => {
      toast.success(`User "${updatedUser.first_name} ${updatedUser.last_name}" has been disabled`)
      
      queryCache.invalidateQueries({ key: TEAM_QUERY_KEYS.user(updatedUser.id) })
      queryCache.invalidateQueries({ key: TEAM_QUERY_KEYS.users })
    },
    onError: (error: any) => {
      const errorMessage = error?.message || 'Failed to disable user'
      toast.error(errorMessage)
    },
  })

  const { mutate: enable, ...enableMutation } = useMutation({
    mutation: (userId: number) => enableWorkspaceUser(userId),
    onSuccess: (updatedUser) => {
      toast.success(`User "${updatedUser.first_name} ${updatedUser.last_name}" has been enabled`)
      
      queryCache.invalidateQueries({ key: TEAM_QUERY_KEYS.user(updatedUser.id) })
      queryCache.invalidateQueries({ key: TEAM_QUERY_KEYS.users })
    },
    onError: (error: any) => {
      const errorMessage = error?.message || 'Failed to enable user'
      toast.error(errorMessage)
    },
  })

  return {
    disableUser: disable,
    enableUser: enable,
    isDisabling: disableMutation.isLoading,
    isEnabling: enableMutation.isLoading,
    isLoading: disableMutation.isLoading.value || enableMutation.isLoading.value,
  }
})