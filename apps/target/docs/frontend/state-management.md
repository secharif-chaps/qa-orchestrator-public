# State Management Guide

This guide covers state management patterns using Pinia in the Basil frontend application, including store structure, best practices, and integration with the API layer.

## 🏪 Pinia Store Architecture

### Store Organization

```
pwa/stores/
├── auth.ts           # Authentication state
├── user.ts           # User management
├── ui.ts             # UI state (modals, notifications)
├── settings.ts       # Application settings
├── workflow.ts       # Business logic state
└── index.ts          # Store registry and types
```

### Store Structure Pattern

```typescript
// stores/example.ts
import { defineStore } from 'pinia'
import type { Example } from '~/types/example'

export const useExampleStore = defineStore('example', () => {
    // State
    const items = ref<Example[]>([])
    const loading = ref(false)
    const error = ref<string | null>(null)
    const selectedItem = ref<Example | null>(null)

    // Getters (computed)
    const itemCount = computed(() => items.value.length)
    const hasItems = computed(() => items.value.length > 0)
    const sortedItems = computed(() =>
        items.value.slice().sort((a, b) => a.name.localeCompare(b.name)),
    )

    // Actions
    const fetchItems = async () => {
        loading.value = true
        error.value = null

        try {
            const { getExamples } = useApi()
            items.value = await getExamples()
        } catch (err) {
            error.value = err instanceof Error ? err.message : 'Failed to fetch items'
        } finally {
            loading.value = false
        }
    }

    const createItem = async (data: Partial<Example>) => {
        try {
            const { createExample } = useApi()
            const newItem = await createExample(data)
            items.value.push(newItem)
            return newItem
        } catch (err) {
            error.value = err instanceof Error ? err.message : 'Failed to create item'
            throw err
        }
    }

    const updateItem = async (id: string, data: Partial<Example>) => {
        try {
            const { updateExample } = useApi()
            const updatedItem = await updateExample(id, data)

            const index = items.value.findIndex((item) => item.id === id)
            if (index !== -1) {
                items.value[index] = updatedItem
            }

            return updatedItem
        } catch (err) {
            error.value = err instanceof Error ? err.message : 'Failed to update item'
            throw err
        }
    }

    const deleteItem = async (id: string) => {
        try {
            const { deleteExample } = useApi()
            await deleteExample(id)

            const index = items.value.findIndex((item) => item.id === id)
            if (index !== -1) {
                items.value.splice(index, 1)
            }
        } catch (err) {
            error.value = err instanceof Error ? err.message : 'Failed to delete item'
            throw err
        }
    }

    const selectItem = (item: Example | null) => {
        selectedItem.value = item
    }

    const clearError = () => {
        error.value = null
    }

    const reset = () => {
        items.value = []
        selectedItem.value = null
        error.value = null
        loading.value = false
    }

    // Return state and actions
    return {
        // State
        items: readonly(items),
        loading: readonly(loading),
        error: readonly(error),
        selectedItem: readonly(selectedItem),

        // Getters
        itemCount,
        hasItems,
        sortedItems,

        // Actions
        fetchItems,
        createItem,
        updateItem,
        deleteItem,
        selectItem,
        clearError,
        reset,
    }
})

// Type inference helper
export type ExampleStore = ReturnType<typeof useExampleStore>
```

## 🔐 Authentication Store

### Auth Store Implementation

```typescript
// stores/auth.ts
import { defineStore } from 'pinia'
import type { User, LoginCredentials, RegisterData } from '~/types/auth'

export const useAuthStore = defineStore(
    'auth',
    () => {
        // State
        const user = ref<User | null>(null)
        const accessToken = ref<string | null>(null)
        const refreshToken = ref<string | null>(null)
        const isAuthenticated = ref(false)
        const loading = ref(false)
        const error = ref<string | null>(null)

        // Getters
        const userDisplayName = computed(() => {
            if (!user.value) return null
            return user.value.firstName && user.value.lastName
                ? `${user.value.firstName} ${user.value.lastName}`
                : user.value.email
        })

        const hasRole = computed(() => (role: string) => {
            return user.value?.roles?.includes(role) ?? false
        })

        const hasPermission = computed(() => (permission: string) => {
            return user.value?.permissions?.includes(permission) ?? false
        })

        // Actions
        const login = async (credentials: LoginCredentials) => {
            loading.value = true
            error.value = null

            try {
                const { login: apiLogin } = useAuth()
                const response = await apiLogin(credentials)

                accessToken.value = response.accessToken
                refreshToken.value = response.refreshToken
                user.value = response.user
                isAuthenticated.value = true

                // Store tokens securely
                await setTokens(response.accessToken, response.refreshToken)

                // Redirect to dashboard
                await navigateTo('/dashboard')
            } catch (err) {
                error.value = err instanceof Error ? err.message : 'Login failed'
                throw err
            } finally {
                loading.value = false
            }
        }

        const register = async (data: RegisterData) => {
            loading.value = true
            error.value = null

            try {
                const { register: apiRegister } = useAuth()
                const response = await apiRegister(data)

                accessToken.value = response.accessToken
                refreshToken.value = response.refreshToken
                user.value = response.user
                isAuthenticated.value = true

                await setTokens(response.accessToken, response.refreshToken)
                await navigateTo('/dashboard')
            } catch (err) {
                error.value = err instanceof Error ? err.message : 'Registration failed'
                throw err
            } finally {
                loading.value = false
            }
        }

        const logout = async () => {
            try {
                const { logout: apiLogout } = useAuth()
                await apiLogout()
            } catch (err) {
                console.warn('Logout API call failed:', err)
            } finally {
                // Clear local state regardless of API call result
                user.value = null
                accessToken.value = null
                refreshToken.value = null
                isAuthenticated.value = false

                await clearTokens()
                await navigateTo('/login')
            }
        }

        const refreshAuth = async () => {
            if (!refreshToken.value) {
                throw new Error('No refresh token available')
            }

            try {
                const { refresh } = useAuth()
                const response = await refresh(refreshToken.value)

                accessToken.value = response.accessToken
                refreshToken.value = response.refreshToken
                user.value = response.user
                isAuthenticated.value = true

                await setTokens(response.accessToken, response.refreshToken)
            } catch (err) {
                // Refresh failed, logout user
                await logout()
                throw err
            }
        }

        const fetchProfile = async () => {
            if (!isAuthenticated.value) return

            try {
                const { getProfile } = useAuth()
                user.value = await getProfile()
            } catch (err) {
                console.error('Failed to fetch profile:', err)
            }
        }

        const updateProfile = async (data: Partial<User>) => {
            if (!user.value) throw new Error('No user logged in')

            try {
                const { updateProfile: apiUpdateProfile } = useAuth()
                user.value = await apiUpdateProfile(data)
                return user.value
            } catch (err) {
                error.value = err instanceof Error ? err.message : 'Profile update failed'
                throw err
            }
        }

        const initialize = async () => {
            const tokens = await getStoredTokens()

            if (tokens.accessToken && tokens.refreshToken) {
                accessToken.value = tokens.accessToken
                refreshToken.value = tokens.refreshToken
                isAuthenticated.value = true

                try {
                    await fetchProfile()
                } catch (err) {
                    // Token invalid, try refresh
                    try {
                        await refreshAuth()
                    } catch (refreshErr) {
                        // Refresh failed, logout
                        await logout()
                    }
                }
            }
        }

        const clearError = () => {
            error.value = null
        }

        return {
            // State
            user: readonly(user),
            accessToken: readonly(accessToken),
            isAuthenticated: readonly(isAuthenticated),
            loading: readonly(loading),
            error: readonly(error),

            // Getters
            userDisplayName,
            hasRole,
            hasPermission,

            // Actions
            login,
            register,
            logout,
            refreshAuth,
            fetchProfile,
            updateProfile,
            initialize,
            clearError,
        }
    },
    {
        persist: {
            storage: persistedState.localStorage,
            paths: ['isAuthenticated'],
        },
    },
)

// Helper functions for token management
const setTokens = async (access: string, refresh: string) => {
    // Store tokens securely (consider using secure storage)
    if (process.client) {
        localStorage.setItem('access_token', access)
        localStorage.setItem('refresh_token', refresh)
    }
}

const getStoredTokens = async () => {
    if (process.client) {
        return {
            accessToken: localStorage.getItem('access_token'),
            refreshToken: localStorage.getItem('refresh_token'),
        }
    }
    return { accessToken: null, refreshToken: null }
}

const clearTokens = async () => {
    if (process.client) {
        localStorage.removeItem('access_token')
        localStorage.removeItem('refresh_token')
    }
}
```

## 👤 User Management Store

```typescript
// stores/user.ts
import { defineStore } from 'pinia'
import type { User, UserFilters, UserSort } from '~/types/user'

export const useUserStore = defineStore('user', () => {
    // State
    const users = ref<User[]>([])
    const loading = ref(false)
    const error = ref<string | null>(null)
    const filters = ref<UserFilters>({
        search: '',
        role: null,
        status: null,
    })
    const sort = ref<UserSort>({
        field: 'createdAt',
        direction: 'desc',
    })
    const pagination = ref({
        page: 1,
        limit: 20,
        total: 0,
    })

    // Getters
    const filteredUsers = computed(() => {
        let filtered = users.value

        // Apply search filter
        if (filters.value.search) {
            const search = filters.value.search.toLowerCase()
            filtered = filtered.filter(
                (user) =>
                    user.email.toLowerCase().includes(search) ||
                    user.firstName?.toLowerCase().includes(search) ||
                    user.lastName?.toLowerCase().includes(search),
            )
        }

        // Apply role filter
        if (filters.value.role) {
            filtered = filtered.filter((user) => user.roles?.includes(filters.value.role!))
        }

        // Apply status filter
        if (filters.value.status !== null) {
            filtered = filtered.filter((user) => user.isActive === filters.value.status)
        }

        return filtered
    })

    const sortedUsers = computed(() => {
        const sorted = [...filteredUsers.value]

        sorted.sort((a, b) => {
            const aValue = a[sort.value.field]
            const bValue = b[sort.value.field]

            if (aValue < bValue) return sort.value.direction === 'asc' ? -1 : 1
            if (aValue > bValue) return sort.value.direction === 'asc' ? 1 : -1
            return 0
        })

        return sorted
    })

    const paginatedUsers = computed(() => {
        const start = (pagination.value.page - 1) * pagination.value.limit
        const end = start + pagination.value.limit
        return sortedUsers.value.slice(start, end)
    })

    const totalPages = computed(() => Math.ceil(sortedUsers.value.length / pagination.value.limit))

    const hasUsers = computed(() => users.value.length > 0)

    // Actions
    const fetchUsers = async (refresh = false) => {
        if (loading.value && !refresh) return

        loading.value = true
        error.value = null

        try {
            const { getUsers } = useApi()
            const response = await getUsers({
                page: pagination.value.page,
                limit: pagination.value.limit,
                ...filters.value,
                sort: sort.value,
            })

            users.value = response.data
            pagination.value.total = response.total
        } catch (err) {
            error.value = err instanceof Error ? err.message : 'Failed to fetch users'
        } finally {
            loading.value = false
        }
    }

    const createUser = async (userData: Partial<User>) => {
        try {
            const { createUser: apiCreateUser } = useApi()
            const newUser = await apiCreateUser(userData)

            users.value.unshift(newUser)
            pagination.value.total += 1

            return newUser
        } catch (err) {
            error.value = err instanceof Error ? err.message : 'Failed to create user'
            throw err
        }
    }

    const updateUser = async (id: string, userData: Partial<User>) => {
        try {
            const { updateUser: apiUpdateUser } = useApi()
            const updatedUser = await apiUpdateUser(id, userData)

            const index = users.value.findIndex((user) => user.id === id)
            if (index !== -1) {
                users.value[index] = updatedUser
            }

            return updatedUser
        } catch (err) {
            error.value = err instanceof Error ? err.message : 'Failed to update user'
            throw err
        }
    }

    const deleteUser = async (id: string) => {
        try {
            const { deleteUser: apiDeleteUser } = useApi()
            await apiDeleteUser(id)

            const index = users.value.findIndex((user) => user.id === id)
            if (index !== -1) {
                users.value.splice(index, 1)
                pagination.value.total -= 1
            }
        } catch (err) {
            error.value = err instanceof Error ? err.message : 'Failed to delete user'
            throw err
        }
    }

    const setFilters = (newFilters: Partial<UserFilters>) => {
        filters.value = { ...filters.value, ...newFilters }
        pagination.value.page = 1 // Reset to first page
    }

    const setSort = (field: keyof User, direction?: 'asc' | 'desc') => {
        if (sort.value.field === field && !direction) {
            sort.value.direction = sort.value.direction === 'asc' ? 'desc' : 'asc'
        } else {
            sort.value = {
                field,
                direction: direction || 'asc',
            }
        }
        pagination.value.page = 1 // Reset to first page
    }

    const setPage = (page: number) => {
        pagination.value.page = Math.max(1, Math.min(page, totalPages.value))
    }

    const clearFilters = () => {
        filters.value = {
            search: '',
            role: null,
            status: null,
        }
        pagination.value.page = 1
    }

    const clearError = () => {
        error.value = null
    }

    const reset = () => {
        users.value = []
        clearFilters()
        pagination.value = { page: 1, limit: 20, total: 0 }
        sort.value = { field: 'createdAt', direction: 'desc' }
        error.value = null
        loading.value = false
    }

    return {
        // State
        users: readonly(users),
        loading: readonly(loading),
        error: readonly(error),
        filters: readonly(filters),
        sort: readonly(sort),
        pagination: readonly(pagination),

        // Getters
        filteredUsers,
        sortedUsers,
        paginatedUsers,
        totalPages,
        hasUsers,

        // Actions
        fetchUsers,
        createUser,
        updateUser,
        deleteUser,
        setFilters,
        setSort,
        setPage,
        clearFilters,
        clearError,
        reset,
    }
})
```

## 🎨 UI State Store

```typescript
// stores/ui.ts
import { defineStore } from 'pinia'

export type NotificationType = 'success' | 'error' | 'warning' | 'info'

export interface Notification {
    id: string
    type: NotificationType
    title: string
    message?: string
    duration?: number
    persistent?: boolean
}

export interface Modal {
    id: string
    component: string
    props?: Record<string, any>
    options?: {
        size?: 'sm' | 'md' | 'lg' | 'xl'
        closable?: boolean
        persistent?: boolean
    }
}

export const useUiStore = defineStore(
    'ui',
    () => {
        // State
        const notifications = ref<Notification[]>([])
        const modals = ref<Modal[]>([])
        const sidebarOpen = ref(false)
        const loading = ref(false)
        const loadingMessage = ref('')

        // Notification actions
        const addNotification = (notification: Omit<Notification, 'id'>) => {
            const id = Math.random().toString(36).substr(2, 9)
            const newNotification: Notification = {
                id,
                duration: 5000,
                persistent: false,
                ...notification,
            }

            notifications.value.push(newNotification)

            // Auto-remove non-persistent notifications
            if (!newNotification.persistent && newNotification.duration) {
                setTimeout(() => {
                    removeNotification(id)
                }, newNotification.duration)
            }

            return id
        }

        const removeNotification = (id: string) => {
            const index = notifications.value.findIndex((n) => n.id === id)
            if (index !== -1) {
                notifications.value.splice(index, 1)
            }
        }

        const clearNotifications = () => {
            notifications.value = []
        }

        // Notification helpers
        const notifySuccess = (title: string, message?: string) => {
            return addNotification({ type: 'success', title, message })
        }

        const notifyError = (title: string, message?: string) => {
            return addNotification({
                type: 'error',
                title,
                message,
                persistent: true,
            })
        }

        const notifyWarning = (title: string, message?: string) => {
            return addNotification({ type: 'warning', title, message })
        }

        const notifyInfo = (title: string, message?: string) => {
            return addNotification({ type: 'info', title, message })
        }

        // Modal actions
        const openModal = (modal: Omit<Modal, 'id'>) => {
            const id = Math.random().toString(36).substr(2, 9)
            const newModal: Modal = { id, ...modal }

            modals.value.push(newModal)
            return id
        }

        const closeModal = (id: string) => {
            const index = modals.value.findIndex((m) => m.id === id)
            if (index !== -1) {
                modals.value.splice(index, 1)
            }
        }

        const closeAllModals = () => {
            modals.value = []
        }

        const getModal = (id: string) => {
            return modals.value.find((m) => m.id === id)
        }

        // Sidebar actions
        const toggleSidebar = () => {
            sidebarOpen.value = !sidebarOpen.value
        }

        const setSidebarOpen = (open: boolean) => {
            sidebarOpen.value = open
        }

        // Loading actions
        const setLoading = (isLoading: boolean, message = '') => {
            loading.value = isLoading
            loadingMessage.value = message
        }

        const startLoading = (message = 'Loading...') => {
            setLoading(true, message)
        }

        const stopLoading = () => {
            setLoading(false, '')
        }

        return {
            // State
            notifications: readonly(notifications),
            modals: readonly(modals),
            sidebarOpen: readonly(sidebarOpen),
            loading: readonly(loading),
            loadingMessage: readonly(loadingMessage),

            // Notification actions
            addNotification,
            removeNotification,
            clearNotifications,
            notifySuccess,
            notifyError,
            notifyWarning,
            notifyInfo,

            // Modal actions
            openModal,
            closeModal,
            closeAllModals,
            getModal,

            // Sidebar actions
            toggleSidebar,
            setSidebarOpen,

            // Loading actions
            setLoading,
            startLoading,
            stopLoading,
        }
    },
    {
        persist: {
            storage: persistedState.localStorage,
            paths: ['sidebarOpen'],
        },
    },
)
```

## ⚙️ Settings Store

```typescript
// stores/settings.ts
import { defineStore } from 'pinia'

export interface AppSettings {
    theme: 'light' | 'dark' | 'system'
    language: string
    timezone: string
    notifications: {
        email: boolean
        push: boolean
        desktop: boolean
    }
    accessibility: {
        reducedMotion: boolean
        highContrast: boolean
        fontSize: 'sm' | 'md' | 'lg'
    }
    layout: {
        sidebar: 'collapsed' | 'expanded'
        density: 'comfortable' | 'compact'
    }
}

export const useSettingsStore = defineStore(
    'settings',
    () => {
        // Default settings
        const defaultSettings: AppSettings = {
            theme: 'system',
            language: 'en',
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
            notifications: {
                email: true,
                push: true,
                desktop: false,
            },
            accessibility: {
                reducedMotion: false,
                highContrast: false,
                fontSize: 'md',
            },
            layout: {
                sidebar: 'expanded',
                density: 'comfortable',
            },
        }

        // State
        const settings = ref<AppSettings>({ ...defaultSettings })
        const loading = ref(false)
        const error = ref<string | null>(null)

        // Getters
        const isDarkMode = computed(() => {
            if (settings.value.theme === 'dark') return true
            if (settings.value.theme === 'light') return false

            // System preference
            if (process.client) {
                return window.matchMedia('(prefers-color-scheme: dark)').matches
            }
            return false
        })

        const isReducedMotion = computed(() => {
            if (settings.value.accessibility.reducedMotion) return true

            if (process.client) {
                return window.matchMedia('(prefers-reduced-motion: reduce)').matches
            }
            return false
        })

        // Actions
        const updateSettings = async (newSettings: Partial<AppSettings>) => {
            const oldSettings = { ...settings.value }

            try {
                // Update local state optimistically
                settings.value = { ...settings.value, ...newSettings }

                // Sync with server
                const { updateUserSettings } = useApi()
                await updateUserSettings(settings.value)

                // Apply theme changes
                if (newSettings.theme && newSettings.theme !== oldSettings.theme) {
                    applyTheme(settings.value.theme)
                }

                // Apply accessibility changes
                if (newSettings.accessibility) {
                    applyAccessibilitySettings(settings.value.accessibility)
                }
            } catch (err) {
                // Revert on error
                settings.value = oldSettings
                error.value = err instanceof Error ? err.message : 'Failed to update settings'
                throw err
            }
        }

        const resetSettings = async () => {
            await updateSettings(defaultSettings)
        }

        const loadSettings = async () => {
            loading.value = true
            error.value = null

            try {
                const { getUserSettings } = useApi()
                const userSettings = await getUserSettings()

                settings.value = { ...defaultSettings, ...userSettings }

                // Apply loaded settings
                applyTheme(settings.value.theme)
                applyAccessibilitySettings(settings.value.accessibility)
            } catch (err) {
                error.value = err instanceof Error ? err.message : 'Failed to load settings'
                console.warn('Failed to load user settings, using defaults')
            } finally {
                loading.value = false
            }
        }

        // Theme management
        const applyTheme = (theme: AppSettings['theme']) => {
            if (!process.client) return

            const root = document.documentElement

            if (theme === 'dark') {
                root.classList.add('dark')
            } else if (theme === 'light') {
                root.classList.remove('dark')
            } else {
                // System preference
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches
                if (prefersDark) {
                    root.classList.add('dark')
                } else {
                    root.classList.remove('dark')
                }
            }
        }

        const applyAccessibilitySettings = (accessibility: AppSettings['accessibility']) => {
            if (!process.client) return

            const root = document.documentElement

            // Reduced motion
            if (accessibility.reducedMotion) {
                root.style.setProperty('--animation-duration', '0.01ms')
            } else {
                root.style.removeProperty('--animation-duration')
            }

            // High contrast
            if (accessibility.highContrast) {
                root.classList.add('high-contrast')
            } else {
                root.classList.remove('high-contrast')
            }

            // Font size
            root.setAttribute('data-font-size', accessibility.fontSize)
        }

        // Initialize settings on app load
        const initialize = async () => {
            await loadSettings()

            // Listen for system theme changes
            if (process.client && settings.value.theme === 'system') {
                const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
                mediaQuery.addEventListener('change', () => {
                    if (settings.value.theme === 'system') {
                        applyTheme('system')
                    }
                })
            }
        }

        return {
            // State
            settings: readonly(settings),
            loading: readonly(loading),
            error: readonly(error),

            // Getters
            isDarkMode,
            isReducedMotion,

            // Actions
            updateSettings,
            resetSettings,
            loadSettings,
            initialize,
        }
    },
    {
        persist: {
            storage: persistedState.localStorage,
            paths: ['settings'],
        },
    },
)
```

## 🔗 Store Composition and Integration

### Store Composables

```typescript
// composables/useStores.ts
export const useStores = () => {
    const authStore = useAuthStore()
    const userStore = useUserStore()
    const uiStore = useUiStore()
    const settingsStore = useSettingsStore()

    return {
        auth: authStore,
        user: userStore,
        ui: uiStore,
        settings: settingsStore,
    }
}

// composables/usePermissions.ts
export const usePermissions = () => {
    const { auth } = useStores()

    const can = (permission: string) => {
        return auth.hasPermission(permission)
    }

    const hasRole = (role: string) => {
        return auth.hasRole(role)
    }

    const canAny = (permissions: string[]) => {
        return permissions.some((permission) => can(permission))
    }

    const canAll = (permissions: string[]) => {
        return permissions.every((permission) => can(permission))
    }

    return {
        can,
        hasRole,
        canAny,
        canAll,
    }
}
```

### Cross-Store Actions

```typescript
// stores/workflow.ts (example of cross-store dependencies)
import { defineStore } from 'pinia'

export const useWorkflowStore = defineStore('workflow', () => {
    const { auth, ui } = useStores()

    const executeWorkflow = async (workflowId: string) => {
        // Check permissions
        if (!auth.hasPermission('workflow:execute')) {
            ui.notifyError('Permission Denied', 'You do not have permission to execute workflows')
            return
        }

        ui.startLoading('Executing workflow...')

        try {
            const { executeWorkflow: apiExecute } = useApi()
            const result = await apiExecute(workflowId)

            ui.notifySuccess('Workflow Executed', 'The workflow has been executed successfully')
            return result
        } catch (err) {
            ui.notifyError(
                'Workflow Failed',
                err instanceof Error ? err.message : 'Failed to execute workflow',
            )
            throw err
        } finally {
            ui.stopLoading()
        }
    }

    return {
        executeWorkflow,
    }
})
```

## 🧪 Testing Stores

### Store Unit Tests

```typescript
// tests/stores/user.test.ts
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useUserStore } from '~/stores/user'

// Mock API
vi.mock('~/composables/useApi', () => ({
    useApi: () => ({
        getUsers: vi.fn(() =>
            Promise.resolve({
                data: [
                    {
                        id: '1',
                        email: 'user1@example.com',
                        firstName: 'John',
                        lastName: 'Doe',
                    },
                    {
                        id: '2',
                        email: 'user2@example.com',
                        firstName: 'Jane',
                        lastName: 'Smith',
                    },
                ],
                total: 2,
            }),
        ),
        createUser: vi.fn((user) => Promise.resolve({ id: '3', ...user })),
        updateUser: vi.fn((id, user) => Promise.resolve({ id, ...user })),
        deleteUser: vi.fn(() => Promise.resolve()),
    }),
}))

describe('User Store', () => {
    beforeEach(() => {
        setActivePinia(createPinia())
    })

    it('should initialize with empty state', () => {
        const store = useUserStore()

        expect(store.users).toEqual([])
        expect(store.loading).toBe(false)
        expect(store.error).toBeNull()
        expect(store.hasUsers).toBe(false)
    })

    it('should fetch users successfully', async () => {
        const store = useUserStore()

        await store.fetchUsers()

        expect(store.users).toHaveLength(2)
        expect(store.users[0].email).toBe('user1@example.com')
        expect(store.hasUsers).toBe(true)
        expect(store.loading).toBe(false)
    })

    it('should filter users by search term', async () => {
        const store = useUserStore()
        await store.fetchUsers()

        store.setFilters({ search: 'john' })

        expect(store.filteredUsers).toHaveLength(1)
        expect(store.filteredUsers[0].firstName).toBe('John')
    })

    it('should handle create user', async () => {
        const store = useUserStore()
        await store.fetchUsers()

        const newUser = await store.createUser({
            email: 'new@example.com',
            firstName: 'New',
            lastName: 'User',
        })

        expect(newUser.id).toBe('3')
        expect(store.users).toHaveLength(3)
        expect(store.users[0].email).toBe('new@example.com') // Added to beginning
    })
})
```

### Integration Tests

```typescript
// tests/integration/auth-flow.test.ts
import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAuthStore } from '~/stores/auth'
import { useUiStore } from '~/stores/ui'

describe('Authentication Flow Integration', () => {
    beforeEach(() => {
        setActivePinia(createPinia())
    })

    it('should handle login flow with notifications', async () => {
        const authStore = useAuthStore()
        const uiStore = useUiStore()

        // Mock successful login
        const loginSpy = vi.spyOn(authStore, 'login').mockResolvedValue()

        await authStore.login({
            email: 'test@example.com',
            password: 'password123',
        })

        expect(loginSpy).toHaveBeenCalledWith({
            email: 'test@example.com',
            password: 'password123',
        })

        expect(authStore.isAuthenticated).toBe(true)
    })
})
```

## 📱 Store Hydration and SSR

### Server-Side Rendering Setup

```typescript
// plugins/pinia.client.ts
export default defineNuxtPlugin(async () => {
    const { auth, settings } = useStores()

    // Initialize auth state from stored tokens
    await auth.initialize()

    // Load user settings
    if (auth.isAuthenticated) {
        await settings.initialize()
    }
})
```

### Hydration Handling

```vue
<!-- pages/dashboard.vue -->
<template>
  <div>
    <div v-if=\"pending\" class=\"loading-state\">
      <div class=\"animate-pulse\">Loading dashboard...</div>
    </div>

    <div v-else class=\"dashboard-content\">
      <!-- Dashboard content -->
    </div>
  </div>
</template>

<script setup lang=\"ts\">
const { auth, user } = useStores()

// Ensure user is authenticated
if (!auth.isAuthenticated) {
  throw createError({
    statusCode: 401,
    statusMessage: 'Authentication required'
  })
}

// Fetch initial data
const { pending } = await useLazyAsyncData('dashboard-data', async () => {
  await Promise.all([
    user.fetchUsers(),
    // ... other initial data
  ])
})
</script>
```

## 🎯 Best Practices

### State Management Guidelines

1. **Keep stores focused** - Each store should handle a specific domain
2. **Use readonly refs** for state exposure to prevent external mutations
3. **Implement proper error handling** in all async actions
4. **Use computed properties** for derived state instead of duplicating data
5. **Persist only necessary data** to avoid bloated localStorage

### Performance Optimization

1. **Lazy load stores** - Only initialize stores when needed
2. **Use shallow refs** for large datasets when appropriate
3. **Implement pagination** for large collections
4. **Debounce search filters** to avoid excessive API calls
5. **Clean up subscriptions** and timers in store actions

### Type Safety

1. **Define proper TypeScript interfaces** for all state shapes
2. **Use generic types** for reusable store patterns
3. **Export store types** for component consumption
4. **Validate API responses** before updating store state
