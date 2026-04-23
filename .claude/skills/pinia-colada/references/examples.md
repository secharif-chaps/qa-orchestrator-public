# Pinia Colada Examples

## Queries

### Basic Query

```vue
<script setup lang="ts">
import { useQuery } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'

const props = defineProps<{ companyId: number }>()

const {
  data: company,
  isLoading,
  error,
  refetch,
} = useQuery(() => companyByIdQuery({ id: props.companyId }))
</script>

<template>
  <div v-if="isLoading" class="flex items-center gap-2">
    <i class="fa fa-spinner fa-spin"></i>
    Loading...
  </div>

  <Alert
    v-else-if="error"
    variant="danger"
    :title="error.message"
    icon="fa-exclamation-circle"
  />

  <div v-else-if="company">
    <h1>{{ company.name }}</h1>
  </div>

  <div v-else class="text-center py-8">
    No company found
  </div>
</template>
```

---

### Paginated Query

```typescript
// src/queries/companies.ts
import { defineQueryOptions } from '@pinia/colada'
import { getCompanies } from '@/api/companies'

export const COMPANY_QUERY_KEYS = {
  root: ['companies'] as const,
  list: (filters: { page: number; size: number; search?: string }) =>
    [...COMPANY_QUERY_KEYS.root, 'list', filters] as const,
}

export const companiesQuery = defineQueryOptions(
  (filters: { page: number; size: number; search?: string }) => ({
    key: COMPANY_QUERY_KEYS.list(filters),
    query: () => getCompanies(filters),
  })
)
```

```vue
<script setup lang="ts">
import { ref, computed } from 'vue'
import { useQuery } from '@pinia/colada'
import { companiesQuery } from '@/queries/companies'
import { Table, Pagination, Searchbar, Alert } from '@owlint/feathers-vue'

// Pagination state
const page = ref(1)
const size = ref(10)
const search = ref('')

// Query automatically refetches when parameters change
const { data, isLoading, error } = useQuery(() =>
  companiesQuery({
    page: page.value,
    size: size.value,
    search: search.value || undefined,
  })
)

// Computed for template
const companies = computed(() => data.value?.items ?? [])
const totalItems = computed(() => data.value?.total ?? 0)

// Handlers
function handleSearch(query: string) {
  search.value = query
  page.value = 1 // Reset to first page on search
}

function handlePageChange(newPage: number) {
  page.value = newPage
}
</script>

<template>
  <div class="flex flex-col gap-6">
    <!-- Search -->
    <Searchbar
      v-model="search"
      placeholder="Search companies..."
      @search="handleSearch"
    />

    <!-- Loading -->
    <div v-if="isLoading" class="flex justify-center py-8">
      <i class="fa fa-spinner fa-spin text-2xl"></i>
    </div>

    <!-- Error -->
    <Alert
      v-else-if="error"
      variant="danger"
      :title="error.message"
      action="Retry"
      @click="refetch"
    />

    <!-- Empty -->
    <div v-else-if="companies.length === 0" class="text-center py-12">
      <i class="fa fa-inbox text-4xl text-gray-400 mb-4"></i>
      <h3 class="text-lg font-semibold">No companies found</h3>
      <p class="text-sm text-gray-600">
        {{ search ? 'Try a different search term' : 'Create your first company' }}
      </p>
    </div>

    <!-- Data -->
    <template v-else>
      <Table
        :fields="[
          { key: 'name', label: 'Name' },
          { key: 'status', label: 'Status' },
          { key: 'createdAt', label: 'Created' },
        ]"
        :items="companies"
      />

      <Pagination
        v-model="page"
        :total="totalItems"
        :per-page="size"
        @update:model-value="handlePageChange"
      />
    </template>
  </div>
</template>
```

---

### Query with Filters

```typescript
// src/queries/tasks.ts
export interface TaskFilters {
  page: number
  size: number
  status?: 'pending' | 'running' | 'completed' | 'failed'
  companyId?: number
  dateFrom?: string
  dateTo?: string
}

export const TASK_QUERY_KEYS = {
  root: ['tasks'] as const,
  list: (filters: TaskFilters) => [...TASK_QUERY_KEYS.root, 'list', filters] as const,
  byCompany: (companyId: number) => [...TASK_QUERY_KEYS.root, 'company', companyId] as const,
}

export const tasksQuery = defineQueryOptions((filters: TaskFilters) => ({
  key: TASK_QUERY_KEYS.list(filters),
  query: () => getTasks(filters),
}))
```

```vue
<script setup lang="ts">
import { ref, reactive } from 'vue'
import { useQuery } from '@pinia/colada'
import { tasksQuery, type TaskFilters } from '@/queries/tasks'

const filters = reactive<TaskFilters>({
  page: 1,
  size: 20,
  status: undefined,
  companyId: undefined,
})

const { data: tasks, isLoading } = useQuery(() => tasksQuery({ ...filters }))

function applyFilter(key: keyof TaskFilters, value: any) {
  filters[key] = value
  filters.page = 1 // Reset pagination
}

function clearFilters() {
  filters.status = undefined
  filters.companyId = undefined
  filters.page = 1
}
</script>
```

---

### Dependent Queries

```vue
<script setup lang="ts">
import { computed } from 'vue'
import { useQuery } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'
import { tasksByCompanyQuery } from '@/queries/tasks'

const props = defineProps<{ companyId: number }>()

// First query: Get company
const { data: company, isLoading: companyLoading } = useQuery(
  () => companyByIdQuery({ id: props.companyId })
)

// Second query: Get tasks — only runs after company is loaded
// Spread query + add enabled inside the same getter to stay reactive
const { data: tasks, isLoading: tasksLoading } = useQuery(() => ({
  ...tasksByCompanyQuery({ companyId: props.companyId }),
  enabled: !!company.value,
}))

const isLoading = computed(() => companyLoading.value || tasksLoading.value)
</script>
```

---

## Mutations

### Basic Mutation

```typescript
// src/mutations/companies.ts
import { defineMutation, useMutation, useQueryCache } from '@pinia/colada'
import { createCompany } from '@/api/companies'
import { COMPANY_QUERY_KEYS } from '@/queries/companies'
import type { CompanyCreate } from '@/types'

export const useCreateCompany = defineMutation(() => {
  const queryCache = useQueryCache()

  const { mutate, mutateAsync, ...mutation } = useMutation({
    mutation: (data: CompanyCreate) => createCompany(data),
    onSuccess() {
      // Invalidate all company queries to refetch fresh data
      queryCache.invalidateQueries({ key: COMPANY_QUERY_KEYS.root })
    },
  })

  return {
    ...mutation,
    createCompany: mutate,
    createCompanyAsync: mutateAsync,
  }
})
```

```vue
<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useCreateCompany } from '@/mutations/companies'
import { Input, Button, Alert } from '@owlint/feathers-vue'

const router = useRouter()
const name = ref('')
const website = ref('')

const { createCompanyAsync, isPending, error } = useCreateCompany()

async function handleSubmit() {
  const company = await createCompanyAsync({
    name: name.value,
    website: website.value,
  })

  // Navigate to new company
  router.push({ name: '/companies/[id]', params: { id: company.id } })
}
</script>

<template>
  <form @submit.prevent="handleSubmit" class="flex flex-col gap-4">
    <Input
      id="name"
      v-model="name"
      label="Company Name"
      placeholder="Enter company name"
      required
    />

    <Input
      id="website"
      v-model="website"
      label="Website"
      placeholder="https://example.com"
    />

    <Alert
      v-if="error"
      variant="danger"
      :title="error.message"
      icon="fa-exclamation-circle"
    />

    <Button
      type="submit"
      label="Create Company"
      variant="primary"
      :loading="isPending"
    />
  </form>
</template>
```

---

### Optimistic Update - Toggle Status

```typescript
// src/mutations/companies.ts
import { defineMutation, useMutation, useQueryCache } from '@pinia/colada'
import { updateCompanyStatus } from '@/api/companies'
import { COMPANY_QUERY_KEYS } from '@/queries/companies'
import type { Company } from '@/types'

export const useToggleCompanyStatus = defineMutation(() => {
  const queryCache = useQueryCache()

  const { mutate, ...mutation } = useMutation({
    mutation: ({ id, status }: { id: number; status: 'active' | 'inactive' }) =>
      updateCompanyStatus(id, status),

    // Optimistic update: immediately update the UI
    onMutate({ id, status }) {
      // Get current data from cache
      const previousData = queryCache.getQueryData<Company>(
        COMPANY_QUERY_KEYS.byId(id)
      )

      // Optimistically update the cache
      if (previousData) {
        queryCache.setQueryData(COMPANY_QUERY_KEYS.byId(id), {
          ...previousData,
          status,
        })
      }

      // Return context for rollback
      return { previousData }
    },

    // Rollback on error
    onError(_error, { id }, context) {
      if (context?.previousData) {
        queryCache.setQueryData(COMPANY_QUERY_KEYS.byId(id), context.previousData)
      }
    },

    // Refetch to ensure consistency
    onSettled(_data, _error, { id }) {
      queryCache.invalidateQueries({ key: COMPANY_QUERY_KEYS.byId(id) })
    },
  })

  return {
    ...mutation,
    toggleStatus: mutate,
  }
})
```

```vue
<script setup lang="ts">
import { useToggleCompanyStatus } from '@/mutations/companies'
import { Switch } from '@owlint/feathers-vue'

const props = defineProps<{
  company: { id: number; status: 'active' | 'inactive' }
}>()

const { toggleStatus, isPending } = useToggleCompanyStatus()

function handleToggle() {
  const newStatus = props.company.status === 'active' ? 'inactive' : 'active'
  toggleStatus({ id: props.company.id, status: newStatus })
}
</script>

<template>
  <div class="flex items-center gap-2">
    <Switch
      :model-value="company.status === 'active'"
      :disabled="isPending"
      @update:model-value="handleToggle"
    />
    <span>{{ company.status === 'active' ? 'Active' : 'Inactive' }}</span>
  </div>
</template>
```

---

### Optimistic Update - Add to List

```typescript
// src/mutations/tasks.ts
import { defineMutation, useMutation, useQueryCache } from '@pinia/colada'
import { createTask } from '@/api/tasks'
import { TASK_QUERY_KEYS } from '@/queries/tasks'
import type { Task, TaskCreate, PaginatedResponse } from '@/types'

export const useCreateTask = defineMutation(() => {
  const queryCache = useQueryCache()

  const { mutate, ...mutation } = useMutation({
    mutation: (data: TaskCreate) => createTask(data),

    onMutate(newTask) {
      // Create optimistic task with temporary ID
      const optimisticTask: Task = {
        id: -Date.now(), // Temporary negative ID
        ...newTask,
        status: 'pending',
        createdAt: new Date().toISOString(),
      }

      // Get all list queries and update them
      const listQueries = queryCache.getQueryCache().findAll({
        key: [...TASK_QUERY_KEYS.root, 'list'],
      })

      const previousDataMap = new Map<string, PaginatedResponse<Task>>()

      listQueries.forEach((query) => {
        const data = query.state.data as PaginatedResponse<Task> | undefined
        if (data) {
          const keyString = JSON.stringify(query.key)
          previousDataMap.set(keyString, data)

          // Add optimistic task to the beginning
          queryCache.setQueryData(query.key, {
            ...data,
            items: [optimisticTask, ...data.items],
            total: data.total + 1,
          })
        }
      })

      return { previousDataMap, optimisticTask }
    },

    onError(_error, _variables, context) {
      // Rollback all list queries
      if (context?.previousDataMap) {
        context.previousDataMap.forEach((data, keyString) => {
          const key = JSON.parse(keyString)
          queryCache.setQueryData(key, data)
        })
      }
    },

    onSuccess(createdTask, _variables, context) {
      // Replace optimistic task with real task
      const listQueries = queryCache.getQueryCache().findAll({
        key: [...TASK_QUERY_KEYS.root, 'list'],
      })

      listQueries.forEach((query) => {
        const data = query.state.data as PaginatedResponse<Task> | undefined
        if (data && context?.optimisticTask) {
          queryCache.setQueryData(query.key, {
            ...data,
            items: data.items.map((task) =>
              task.id === context.optimisticTask.id ? createdTask : task
            ),
          })
        }
      })
    },

    onSettled() {
      // Refetch to ensure consistency
      queryCache.invalidateQueries({ key: TASK_QUERY_KEYS.root })
    },
  })

  return {
    ...mutation,
    createTask: mutate,
  }
})
```

---

### Optimistic Update - Delete from List

```typescript
// src/mutations/companies.ts
export const useDeleteCompany = defineMutation(() => {
  const queryCache = useQueryCache()

  const { mutate, ...mutation } = useMutation({
    mutation: (id: number) => deleteCompany(id),

    onMutate(deletedId) {
      // Store previous data for rollback
      const previousCompany = queryCache.getQueryData<Company>(
        COMPANY_QUERY_KEYS.byId(deletedId)
      )

      // Remove from single query cache
      queryCache.removeQueries({ key: COMPANY_QUERY_KEYS.byId(deletedId) })

      // Remove from all list queries
      const listQueries = queryCache.getQueryCache().findAll({
        key: [...COMPANY_QUERY_KEYS.root, 'list'],
      })

      const previousLists = new Map()

      listQueries.forEach((query) => {
        const data = query.state.data as PaginatedResponse<Company> | undefined
        if (data) {
          previousLists.set(JSON.stringify(query.key), data)

          queryCache.setQueryData(query.key, {
            ...data,
            items: data.items.filter((c) => c.id !== deletedId),
            total: data.total - 1,
          })
        }
      })

      return { previousCompany, previousLists, deletedId }
    },

    onError(_error, _id, context) {
      // Rollback single company
      if (context?.previousCompany) {
        queryCache.setQueryData(
          COMPANY_QUERY_KEYS.byId(context.deletedId),
          context.previousCompany
        )
      }

      // Rollback lists
      if (context?.previousLists) {
        context.previousLists.forEach((data: any, keyString: string) => {
          queryCache.setQueryData(JSON.parse(keyString), data)
        })
      }
    },

    onSettled() {
      queryCache.invalidateQueries({ key: COMPANY_QUERY_KEYS.root })
    },
  })

  return {
    ...mutation,
    deleteCompany: mutate,
  }
})
```

```vue
<script setup lang="ts">
import { ref } from 'vue'
import { useDeleteCompany } from '@/mutations/companies'
import { Modal, Button } from '@owlint/feathers-vue'

const props = defineProps<{ company: { id: number; name: string } }>()
interface Emits {
  deleted: []
}

const emit = defineEmits<Emits>()

const showConfirm = ref(false)
const { deleteCompany, isPending, error } = useDeleteCompany()

async function handleDelete() {
  await deleteCompany(props.company.id)
  showConfirm.value = false
  emit('deleted')
}
</script>

<template>
  <Button
    label="Delete"
    variant="secondary"
    intent="danger"
    icon="fa-trash"
    @click="showConfirm = true"
  />

  <Modal
    v-model:displayModal="showConfirm"
    title="Delete Company"
    icon="fa-trash"
    size="sm"
  >
    <template #description>
      Are you sure you want to delete <strong>{{ company.name }}</strong>?
      This action cannot be undone.
    </template>

    <template #footer>
      <Button
        label="Cancel"
        variant="secondary"
        @click="showConfirm = false"
      />
      <Button
        label="Delete"
        variant="primary"
        intent="danger"
        :loading="isPending"
        @click="handleDelete"
      />
    </template>
  </Modal>
</template>
```

---

### Optimistic Update - Inline Edit

```typescript
// src/mutations/companies.ts
export const useUpdateCompanyName = defineMutation(() => {
  const queryCache = useQueryCache()

  const { mutate, ...mutation } = useMutation({
    mutation: ({ id, name }: { id: number; name: string }) =>
      patchCompany(id, { name }),

    onMutate({ id, name }) {
      const previousCompany = queryCache.getQueryData<Company>(
        COMPANY_QUERY_KEYS.byId(id)
      )

      // Optimistically update
      if (previousCompany) {
        queryCache.setQueryData(COMPANY_QUERY_KEYS.byId(id), {
          ...previousCompany,
          name,
        })
      }

      return { previousCompany }
    },

    onError(_error, { id }, context) {
      if (context?.previousCompany) {
        queryCache.setQueryData(COMPANY_QUERY_KEYS.byId(id), context.previousCompany)
      }
    },

    onSettled(_data, _error, { id }) {
      queryCache.invalidateQueries({ key: COMPANY_QUERY_KEYS.byId(id) })
      queryCache.invalidateQueries({ key: [...COMPANY_QUERY_KEYS.root, 'list'] })
    },
  })

  return {
    ...mutation,
    updateName: mutate,
  }
})
```

```vue
<script setup lang="ts">
import { ref, watch } from 'vue'
import { useUpdateCompanyName } from '@/mutations/companies'
import { Input, Button } from '@owlint/feathers-vue'

const props = defineProps<{ company: { id: number; name: string } }>()

const isEditing = ref(false)
const editedName = ref(props.company.name)

const { updateName, isPending } = useUpdateCompanyName()

// Reset on company change
watch(() => props.company.name, (newName) => {
  editedName.value = newName
})

function startEdit() {
  editedName.value = props.company.name
  isEditing.value = true
}

function cancelEdit() {
  editedName.value = props.company.name
  isEditing.value = false
}

function saveEdit() {
  if (editedName.value.trim() && editedName.value !== props.company.name) {
    updateName({ id: props.company.id, name: editedName.value.trim() })
  }
  isEditing.value = false
}
</script>

<template>
  <div class="flex items-center gap-2">
    <template v-if="isEditing">
      <Input
        id="company-name"
        v-model="editedName"
        size="sm"
        @keyup.enter="saveEdit"
        @keyup.escape="cancelEdit"
      />
      <Button
        icon="fa-check"
        variant="tertiary"
        size="sm"
        :loading="isPending"
        @click="saveEdit"
      />
      <Button
        icon="fa-times"
        variant="tertiary"
        size="sm"
        @click="cancelEdit"
      />
    </template>

    <template v-else>
      <span>{{ company.name }}</span>
      <Button
        icon="fa-pencil"
        variant="tertiary"
        size="sm"
        @click="startEdit"
      />
    </template>
  </div>
</template>
```

---

## Query Cache Utilities

### Manual Cache Updates

```typescript
import { useQueryCache } from '@pinia/colada'

const queryCache = useQueryCache()

// Get cached data
const company = queryCache.getQueryData<Company>(COMPANY_QUERY_KEYS.byId(123))

// Set cached data
queryCache.setQueryData(COMPANY_QUERY_KEYS.byId(123), updatedCompany)

// Invalidate queries (triggers refetch)
queryCache.invalidateQueries({ key: COMPANY_QUERY_KEYS.root })

// Remove queries from cache
queryCache.removeQueries({ key: COMPANY_QUERY_KEYS.byId(123) })

// Prefetch data
queryCache.prefetchQuery(companyByIdQuery, { id: 123 })
```

### Prefetching on Hover

```vue
<script setup lang="ts">
import { useQueryCache } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'

const queryCache = useQueryCache()

function prefetchCompany(id: number) {
  queryCache.prefetchQuery(companyByIdQuery, { id })
}
</script>

<template>
  <RouterLink
    v-for="company in companies"
    :key="company.id"
    :to="{ name: '/companies/[id]', params: { id: company.id } }"
    @mouseenter="prefetchCompany(company.id)"
  >
    {{ company.name }}
  </RouterLink>
</template>
```
