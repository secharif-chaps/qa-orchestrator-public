# Vuellar Component Examples

## Forms

### Basic Form
```vue
<script setup lang="ts">
import { ref } from 'vue'
import { Input, Textarea, Button, Alert } from '@owlint/feathers-vue'

const name = ref('')
const email = ref('')
const message = ref('')
const error = ref('')
const isSubmitting = ref(false)

async function handleSubmit() {
  isSubmitting.value = true
  error.value = ''

  try {
    await api.submit({ name: name.value, email: email.value, message: message.value })
  } catch (e) {
    error.value = (e as Error).message
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <form @submit.prevent="handleSubmit" class="flex flex-col gap-4">
    <Input
      id="name"
      v-model="name"
      label="Name"
      placeholder="Your name"
      required
    />

    <Input
      id="email"
      v-model="email"
      type="email"
      label="Email"
      placeholder="you@example.com"
      required
    />

    <Textarea
      id="message"
      v-model="message"
      label="Message"
      placeholder="Your message..."
      rows="4"
    />

    <Alert
      v-if="error"
      variant="danger"
      :title="error"
      icon="fa-exclamation-circle"
    />

    <Button
      type="submit"
      label="Submit"
      variant="primary"
      :loading="isSubmitting"
    />
  </form>
</template>
```

### Form with Validation
```vue
<script setup lang="ts">
import { ref, computed } from 'vue'
import { Input, Button, Alert } from '@owlint/feathers-vue'

const email = ref('')
const password = ref('')
const emailError = ref('')
const passwordError = ref('')

const isValid = computed(() => {
  return email.value && password.value && !emailError.value && !passwordError.value
})

function validateEmail() {
  if (!email.value) {
    emailError.value = 'Email is required'
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
    emailError.value = 'Invalid email format'
  } else {
    emailError.value = ''
  }
}

function validatePassword() {
  if (!password.value) {
    passwordError.value = 'Password is required'
  } else if (password.value.length < 8) {
    passwordError.value = 'Password must be at least 8 characters'
  } else {
    passwordError.value = ''
  }
}
</script>

<template>
  <form @submit.prevent="handleSubmit" class="flex flex-col gap-4">
    <Input
      id="email"
      v-model="email"
      type="email"
      label="Email"
      placeholder="you@example.com"
      :error="emailError"
      @blur="validateEmail"
      required
    />

    <Input
      id="password"
      v-model="password"
      type="password"
      label="Password"
      placeholder="••••••••"
      :error="passwordError"
      @blur="validatePassword"
      required
    />

    <Button
      type="submit"
      label="Sign In"
      variant="primary"
      :disabled="!isValid"
    />
  </form>
</template>
```

---

## Tables

### Basic Table
```vue
<script setup lang="ts">
import { Table, Tag } from '@owlint/feathers-vue'

const fields = [
  { key: 'name', label: 'Name' },
  { key: 'email', label: 'Email' },
  { key: 'status', label: 'Status' },
]

const users = [
  { id: 1, name: 'John Doe', email: 'john@example.com', status: 'active' },
  { id: 2, name: 'Jane Smith', email: 'jane@example.com', status: 'pending' },
  { id: 3, name: 'Bob Johnson', email: 'bob@example.com', status: 'inactive' },
]
</script>

<template>
  <Table :fields="fields" :items="users">
    <template #cell(status)="{ value }">
      <Tag
        :label="value"
        :intent="value === 'active' ? 'success' : value === 'pending' ? 'warning' : 'neutral'"
      />
    </template>
  </Table>
</template>
```

### Table with Actions
```vue
<script setup lang="ts">
import { ref } from 'vue'
import { Table, Tag, Menu, Button } from '@owlint/feathers-vue'

const fields = [
  { key: 'name', label: 'Name' },
  { key: 'email', label: 'Email' },
  { key: 'role', label: 'Role' },
  { key: 'actions', label: '', class: 'w-16' },
]

const users = ref([
  { id: 1, name: 'John Doe', email: 'john@example.com', role: 'Admin', menuOpen: false },
])

function handleEdit(user: typeof users.value[0]) {
  console.log('Edit:', user)
}

function handleDelete(user: typeof users.value[0]) {
  console.log('Delete:', user)
}
</script>

<template>
  <Table :fields="fields" :items="users">
    <template #cell(role)="{ value }">
      <Tag :label="value" variant="secondary" />
    </template>

    <template #cell(actions)="{ item }">
      <Menu
        v-model="item.menuOpen"
        :items="[
          { label: 'Edit', onClick: () => handleEdit(item) },
          { label: 'Delete', onClick: () => handleDelete(item) },
        ]"
      >
        <template #trigger>
          <Button icon="fa-ellipsis-v" variant="tertiary" size="sm" />
        </template>
      </Menu>
    </template>
  </Table>
</template>
```

### Table with Loading State
```vue
<script setup lang="ts">
import { Table } from '@owlint/feathers-vue'

const props = defineProps<{
  isLoading: boolean
  users: User[]
}>()

const fields = [
  { key: 'name', label: 'Name' },
  { key: 'email', label: 'Email' },
]
</script>

<template>
  <Table
    :fields="fields"
    :items="users"
    :loading="isLoading"
    :skeleton-rows="5"
  />
</template>
```

---

## Modals

### Confirmation Modal
```vue
<script setup lang="ts">
import { ref } from 'vue'
import { Modal, Button } from '@owlint/feathers-vue'

const showModal = ref(false)
const isDeleting = ref(false)

async function confirmDelete() {
  isDeleting.value = true
  try {
    await api.deleteItem(itemId)
    showModal.value = false
  } finally {
    isDeleting.value = false
  }
}
</script>

<template>
  <Button
    label="Delete"
    variant="secondary"
    icon="fa-trash"
    @click="showModal = true"
  />

  <Modal
    v-model:displayModal="showModal"
    title="Confirm Delete"
    icon="fa-trash"
    size="sm"
    @close="showModal = false"
  >
    <template #description>
      Are you sure you want to delete this item? This action cannot be undone.
    </template>

    <template #footer>
      <Button
        label="Cancel"
        variant="secondary"
        @click="showModal = false"
      />
      <Button
        label="Delete"
        variant="primary"
        intent="danger"
        :loading="isDeleting"
        @click="confirmDelete"
      />
    </template>
  </Modal>
</template>
```

### Form Modal
```vue
<script setup lang="ts">
import { ref } from 'vue'
import { Modal, Button, Input } from '@owlint/feathers-vue'

const showModal = ref(false)
const name = ref('')
const email = ref('')
const isSaving = ref(false)

async function handleSave() {
  isSaving.value = true
  try {
    await api.createUser({ name: name.value, email: email.value })
    showModal.value = false
    name.value = ''
    email.value = ''
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <Button
    label="Add User"
    variant="primary"
    icon="fa-plus"
    @click="showModal = true"
  />

  <Modal
    v-model:displayModal="showModal"
    title="Add New User"
    icon="fa-user-plus"
    size="md"
    @close="showModal = false"
  >
    <div class="flex flex-col gap-4">
      <Input
        id="name"
        v-model="name"
        label="Name"
        placeholder="Full name"
        required
      />

      <Input
        id="email"
        v-model="email"
        type="email"
        label="Email"
        placeholder="email@example.com"
        required
      />
    </div>

    <template #footer>
      <Button
        label="Cancel"
        variant="secondary"
        @click="showModal = false"
      />
      <Button
        label="Save"
        variant="primary"
        :loading="isSaving"
        @click="handleSave"
      />
    </template>
  </Modal>
</template>
```

---

## Alerts & Feedback

### Alert Variants
```vue
<template>
  <div class="flex flex-col gap-4">
    <!-- Success -->
    <Alert
      variant="success"
      title="Success!"
      description="Your changes have been saved."
      icon="fa-check-circle"
    />

    <!-- Warning -->
    <Alert
      variant="warning"
      title="Warning"
      description="This action cannot be undone."
      icon="fa-exclamation-triangle"
    />

    <!-- Danger/Error -->
    <Alert
      variant="danger"
      title="Error"
      description="Something went wrong. Please try again."
      icon="fa-exclamation-circle"
    />

    <!-- Info -->
    <Alert
      variant="info"
      title="Info"
      description="Your session will expire in 5 minutes."
      icon="fa-info-circle"
    />

    <!-- With Action -->
    <Alert
      variant="warning"
      title="Unsaved Changes"
      description="You have unsaved changes that will be lost."
      action="Save Now"
      @click="handleSave"
    />
  </div>
</template>
```

---

## Navigation

### Tabs
```vue
<script setup lang="ts">
import { ref } from 'vue'
import { Tab } from '@owlint/feathers-vue'

const activeTab = ref('overview')

const tabs = [
  { id: 'overview', title: 'Overview', isActive: activeTab.value === 'overview' },
  { id: 'details', title: 'Details', isActive: activeTab.value === 'details' },
  { id: 'settings', title: 'Settings', icon: 'fa-cog', isActive: activeTab.value === 'settings' },
]

function handleTabClick(tabId: string) {
  activeTab.value = tabId
}
</script>

<template>
  <Tab
    :tabs="tabs.map(t => ({
      ...t,
      isActive: activeTab === t.id,
      click: () => handleTabClick(t.id)
    }))"
    variant="secondary"
  />

  <!-- Tab content -->
  <div v-if="activeTab === 'overview'">Overview content</div>
  <div v-else-if="activeTab === 'details'">Details content</div>
  <div v-else-if="activeTab === 'settings'">Settings content</div>
</template>
```

### Breadcrumbs
```vue
<script setup lang="ts">
import { Breadcrumb } from '@owlint/feathers-vue'

const breadcrumbs = [
  { label: 'Home', to: '/' },
  { label: 'Companies', to: '/companies' },
  { label: 'Acme Corp' },  // Current page (no link)
]
</script>

<template>
  <Breadcrumb :items="breadcrumbs" />
</template>
```

### Pagination
```vue
<script setup lang="ts">
import { ref } from 'vue'
import { Pagination } from '@owlint/feathers-vue'

const currentPage = ref(1)
const totalItems = 100
const perPage = 10
</script>

<template>
  <Pagination
    v-model="currentPage"
    :total="totalItems"
    :per-page="perPage"
  />
</template>
```

---

## Selection Controls

### Checkbox Group
```vue
<script setup lang="ts">
import { ref } from 'vue'
import { Checkbox } from '@owlint/feathers-vue'

const selectedFeatures = ref<string[]>([])
</script>

<template>
  <div class="flex flex-col gap-2">
    <Checkbox
      id="feature-a"
      v-model="selectedFeatures"
      value="feature-a"
      label="Feature A"
    />
    <Checkbox
      id="feature-b"
      v-model="selectedFeatures"
      value="feature-b"
      label="Feature B"
    />
    <Checkbox
      id="feature-c"
      v-model="selectedFeatures"
      value="feature-c"
      label="Feature C"
    />
  </div>
</template>
```

### Radio Group
```vue
<script setup lang="ts">
import { ref } from 'vue'
import { Radio } from '@owlint/feathers-vue'

const selectedSize = ref('medium')
</script>

<template>
  <div class="flex flex-col gap-2">
    <Radio
      id="size-sm"
      v-model="selectedSize"
      value="small"
      name="size"
      label="Small"
    />
    <Radio
      id="size-md"
      v-model="selectedSize"
      value="medium"
      name="size"
      label="Medium"
    />
    <Radio
      id="size-lg"
      v-model="selectedSize"
      value="large"
      name="size"
      label="Large"
    />
  </div>
</template>
```

### Select Dropdown
```vue
<script setup lang="ts">
import { ref } from 'vue'
import { Select } from '@owlint/feathers-vue'

const selectedCountry = ref('')
const countries = ['France', 'Germany', 'Spain', 'Italy', 'United Kingdom']
</script>

<template>
  <Select
    v-model="selectedCountry"
    :options="countries"
    placeholder="Select a country"
  />
</template>
```

### Toggle Switch
```vue
<script setup lang="ts">
import { ref } from 'vue'
import { Switch, Label } from '@owlint/feathers-vue'

const notifications = ref(true)
const darkMode = ref(false)
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
      <Label id="notifications">Email Notifications</Label>
      <Switch id="notifications" v-model="notifications" />
    </div>

    <div class="flex items-center justify-between">
      <Label id="dark-mode">Dark Mode</Label>
      <Switch id="dark-mode" v-model="darkMode" />
    </div>
  </div>
</template>
```

### Controlled Switch (with async handler)
Use this pattern when you need to perform an async action on toggle:
```vue
<script setup lang="ts">
import { Switch } from '@owlint/feathers-vue'

const props = defineProps<{
  isEnabled: boolean
  isLoading: boolean
}>()

const emit = defineEmits<{
  toggle: []
}>()

function handleToggle() {
  // Parent handles the actual toggle logic
  emit('toggle')
}
</script>

<template>
  <Switch
    id="module-toggle"
    :model-value="isEnabled"
    :disabled="isLoading"
    @update:model-value="handleToggle"
  />
</template>
```

**Key points:**
- Use `:model-value` (not `v-model`) when you need controlled mode
- Use `@update:model-value` to handle changes (NOT `@change`)
- The parent component manages the actual state

---

## Empty & Loading States

### Empty State
```vue
<script setup lang="ts">
import { Button } from '@owlint/feathers-vue'
</script>

<template>
  <div class="text-center py-12">
    <i class="fa fa-inbox text-4xl text-gray-400 mb-4"></i>
    <h3 class="text-lg font-semibold text-gray-900 mb-2">
      No companies yet
    </h3>
    <p class="text-sm text-gray-600 mb-6">
      Get started by adding your first company
    </p>
    <Button
      variant="primary"
      icon="fa-plus"
      label="Add Company"
      @click="handleCreate"
    />
  </div>
</template>
```

### Loading State
```vue
<template>
  <div class="flex items-center justify-center py-12">
    <div class="flex items-center gap-3">
      <i class="fa fa-spinner fa-spin text-2xl text-primary"></i>
      <span class="text-gray-600">Loading...</span>
    </div>
  </div>
</template>
```
