# Components Guide

This guide covers Vue component development patterns, conventions, and best practices for the Basil frontend application.

## 🏗️ Component Architecture

### Component Organization

```text
pwa/components/
├── Base/               # Generic, reusable components
│   ├── BaseButton.vue
│   ├── BaseInput.vue
│   ├── BaseModal.vue
│   └── BaseCard.vue
├── Form/               # Form-specific components
│   ├── FormField.vue
│   ├── FormValidation.vue
│   └── FormSubmit.vue
├── Layout/             # Layout and navigation components
│   ├── LayoutHeader.vue
│   ├── LayoutSidebar.vue
│   └── LayoutFooter.vue
├── Business/           # Domain-specific components
│   ├── UserProfile.vue
│   ├── UserList.vue
│   └── UserCard.vue
└── Icon/               # Icon components
    ├── IconUser.vue
    ├── IconSettings.vue
    └── IconDashboard.vue
```

### Component Naming Conventions

- **PascalCase**: All component names use PascalCase
- **Descriptive Names**: Names should clearly indicate the component's purpose
- **Prefix-based**: Use prefixes to indicate component category
  - `Base-`: Generic, reusable components
  - `Form-`: Form-related components
  - `Layout-`: Layout and structural components
  - `Icon-`: Icon components
  - `Business-`: Domain-specific components

## 🧩 Base Components

Base components are generic, highly reusable UI elements that form the foundation of the design system.

### BaseButton Component

```vue
<template>
  <button
    :class=\"buttonClasses\"
    :disabled=\"disabled || loading\"
    :type=\"type\"
    @click=\"handleClick\"
  >
    <Icon
      v-if=\"loading\"
      name=\"loader\"
      class=\"animate-spin mr-2\"
    />
    <Icon
      v-else-if=\"icon && iconPosition === 'left'\"
      :name=\"icon\"
      class=\"mr-2\"
    />

    <span v-if=\"$slots.default\">
      <slot />
    </span>

    <Icon
      v-if=\"icon && iconPosition === 'right'\"
      :name=\"icon\"
      class=\"ml-2\"
    />
  </button>
</template>

<script setup lang=\"ts\">
interface Props {
  variant?: 'primary' | 'secondary' | 'outline' | 'ghost' | 'danger'
  size?: 'sm' | 'md' | 'lg'
  disabled?: boolean
  loading?: boolean
  type?: 'button' | 'submit' | 'reset'
  icon?: string
  iconPosition?: 'left' | 'right'
  fullWidth?: boolean
}

interface Emits {
  click: [event: MouseEvent]
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'primary',
  size: 'md',
  disabled: false,
  loading: false,
  type: 'button',
  iconPosition: 'left',
  fullWidth: false
})

const emit = defineEmits<Emits>()

const buttonClasses = computed(() => [
  // Base classes
  'inline-flex items-center justify-center font-medium rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2',

  // Size variants
  {
    'px-3 py-1.5 text-sm': props.size === 'sm',
    'px-4 py-2 text-base': props.size === 'md',
    'px-6 py-3 text-lg': props.size === 'lg'
  },

  // Color variants
  {
    'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500': props.variant === 'primary',
    'bg-gray-600 text-white hover:bg-gray-700 focus:ring-gray-500': props.variant === 'secondary',
    'border border-gray-300 text-gray-700 hover:bg-gray-50 focus:ring-blue-500': props.variant === 'outline',
    'text-gray-700 hover:bg-gray-100 focus:ring-blue-500': props.variant === 'ghost',
    'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500': props.variant === 'danger'
  },

  // State variants
  {
    'opacity-50 cursor-not-allowed': props.disabled || props.loading,
    'w-full': props.fullWidth
  }
])

const handleClick = (event: MouseEvent) => {
  if (!props.disabled && !props.loading) {
    emit('click', event)
  }
}
</script>
```

### BaseInput Component

```vue
<template>
  <div class=\"base-input\">
    <label
      v-if=\"label\"
      :for=\"inputId\"
      class=\"block text-sm font-medium text-gray-700 mb-1\"
    >
      {{ label }}
      <span v-if=\"required\" class=\"text-red-500 ml-1\">*</span>
    </label>

    <div class=\"relative\">
      <input
        :id=\"inputId\"
        v-model=\"inputValue\"
        :type=\"type\"
        :placeholder=\"placeholder\"
        :disabled=\"disabled\"
        :readonly=\"readonly\"
        :class=\"inputClasses\"
        v-bind=\"$attrs\"
        @blur=\"handleBlur\"
        @focus=\"handleFocus\"
        @input=\"handleInput\"
      />

      <Icon
        v-if=\"icon\"
        :name=\"icon\"
        class=\"absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400\"
      />
    </div>

    <p
      v-if=\"error\"
      class=\"mt-1 text-sm text-red-600\"
    >
      {{ error }}
    </p>

    <p
      v-else-if=\"hint\"
      class=\"mt-1 text-sm text-gray-500\"
    >
      {{ hint }}
    </p>
  </div>
</template>

<script setup lang=\"ts\">
interface Props {
  modelValue?: string | number
  label?: string
  placeholder?: string
  type?: 'text' | 'email' | 'password' | 'number' | 'tel' | 'url'
  error?: string
  hint?: string
  disabled?: boolean
  readonly?: boolean
  required?: boolean
  icon?: string
}

interface Emits {
  'update:modelValue': [value: string | number]
  blur: [event: FocusEvent]
  focus: [event: FocusEvent]
  input: [event: Event]
}

const props = withDefaults(defineProps<Props>(), {
  type: 'text',
  disabled: false,
  readonly: false,
  required: false
})

const emit = defineEmits<Emits>()

// Generate unique ID for accessibility
const inputId = computed(() => `input-${Math.random().toString(36).substr(2, 9)}`)

const inputValue = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const inputClasses = computed(() => [
  'w-full px-3 py-2 border rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-1 transition-colors',
  {
    'border-red-300 focus:border-red-500 focus:ring-red-500': props.error,
    'border-gray-300 focus:border-blue-500 focus:ring-blue-500': !props.error,
    'bg-gray-50 cursor-not-allowed': props.disabled,
    'bg-gray-50': props.readonly,
    'pr-10': props.icon
  }
])

const handleBlur = (event: FocusEvent) => {
  emit('blur', event)
}

const handleFocus = (event: FocusEvent) => {
  emit('focus', event)
}

const handleInput = (event: Event) => {
  emit('input', event)
}
</script>
```

### BaseModal Component

```vue
<template>
  <Teleport to=\"body\">
    <Transition
      enter-active-class=\"transition-opacity duration-300\"
      enter-from-class=\"opacity-0\"
      enter-to-class=\"opacity-100\"
      leave-active-class=\"transition-opacity duration-300\"
      leave-from-class=\"opacity-100\"
      leave-to-class=\"opacity-0\"
    >
      <div
        v-if=\"isOpen\"
        class=\"fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50\"
        @click=\"handleOverlayClick\"
      >
        <Transition
          enter-active-class=\"transition-all duration-300\"
          enter-from-class=\"opacity-0 scale-95 translate-y-4\"
          enter-to-class=\"opacity-100 scale-100 translate-y-0\"
          leave-active-class=\"transition-all duration-300\"
          leave-from-class=\"opacity-100 scale-100 translate-y-0\"
          leave-to-class=\"opacity-0 scale-95 translate-y-4\"
        >
          <div
            v-if=\"isOpen\"
            :class=\"modalClasses\"
            role=\"dialog\"
            aria-modal=\"true\"
            :aria-labelledby=\"titleId\"
            @click.stop
          >
            <div class=\"flex items-center justify-between p-6 border-b border-gray-200\">
              <h2
                :id=\"titleId\"
                class=\"text-lg font-semibold text-gray-900\"
              >
                {{ title }}
              </h2>

              <button
                v-if=\"closable\"
                type=\"button\"
                class=\"text-gray-400 hover:text-gray-600 transition-colors\"
                @click=\"close\"
              >
                <Icon name=\"x\" class=\"w-6 h-6\" />
              </button>
            </div>

            <div class=\"p-6\">
              <slot />
            </div>

            <div
              v-if=\"$slots.footer\"
              class=\"flex justify-end gap-3 p-6 border-t border-gray-200\"
            >
              <slot name=\"footer\" />
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang=\"ts\">
interface Props {
  isOpen: boolean
  title?: string
  size?: 'sm' | 'md' | 'lg' | 'xl'
  closable?: boolean
  closeOnOverlay?: boolean
}

interface Emits {
  close: []
}

const props = withDefaults(defineProps<Props>(), {
  size: 'md',
  closable: true,
  closeOnOverlay: true
})

const emit = defineEmits<Emits>()

const titleId = computed(() => `modal-title-${Math.random().toString(36).substr(2, 9)}`)

const modalClasses = computed(() => [
  'bg-white rounded-lg shadow-xl max-h-screen overflow-y-auto',
  {
    'max-w-sm': props.size === 'sm',
    'max-w-md': props.size === 'md',
    'max-w-2xl': props.size === 'lg',
    'max-w-4xl': props.size === 'xl'
  }
])

const close = () => {
  emit('close')
}

const handleOverlayClick = () => {
  if (props.closeOnOverlay) {
    close()
  }
}

// Handle escape key
onMounted(() => {
  const handleEscape = (event: KeyboardEvent) => {
    if (event.key === 'Escape' && props.isOpen && props.closable) {
      close()
    }
  }

  document.addEventListener('keydown', handleEscape)

  onUnmounted(() => {
    document.removeEventListener('keydown', handleEscape)
  })
})

// Focus management
watchEffect(() => {
  if (props.isOpen) {
    document.body.style.overflow = 'hidden'
  } else {
    document.body.style.overflow = ''
  }
})
</script>
```

## 🏢 Business Components

Business components are domain-specific components that handle particular business logic or data.

### UserCard Component

```vue
<template>
  <BaseCard class=\"user-card\">
    <div class=\"flex items-center space-x-4\">
      <div class=\"shrink-0\">
        <img
          v-if=\"user.avatar\"
          :src=\"user.avatar\"
          :alt=\"userDisplayName\"
          class=\"w-12 h-12 rounded-full object-cover\"
        />
        <div
          v-else
          class=\"w-12 h-12 rounded-full bg-gray-300 flex items-center justify-center\"
        >
          <Icon name=\"user\" class=\"w-6 h-6 text-gray-600\" />
        </div>
      </div>

      <div class=\"flex-1 min-w-0\">
        <p class=\"text-sm font-medium text-gray-900 truncate\">
          {{ userDisplayName }}
        </p>
        <p class=\"text-sm text-gray-500 truncate\">
          {{ user.email }}
        </p>
        <p v-if=\"user.role\" class=\"text-xs text-gray-400\">
          {{ user.role }}
        </p>
      </div>

      <div class=\"shrink-0\">
        <BaseButton
          v-if=\"editable\"
          variant=\"outline\"
          size=\"sm\"
          icon=\"edit\"
          data-testid=\"edit-button\"
          @click=\"handleEdit\"
        >
          Edit
        </BaseButton>
      </div>
    </div>

    <div v-if=\"showDetails\" class=\"mt-4 pt-4 border-t border-gray-200\">
      <dl class=\"grid grid-cols-1 gap-x-4 gap-y-2 sm:grid-cols-2\">
        <div v-if=\"user.firstName\">
          <dt class=\"text-sm font-medium text-gray-500\">First Name</dt>
          <dd class=\"text-sm text-gray-900\">{{ user.firstName }}</dd>
        </div>
        <div v-if=\"user.lastName\">
          <dt class=\"text-sm font-medium text-gray-500\">Last Name</dt>
          <dd class=\"text-sm text-gray-900\">{{ user.lastName }}</dd>
        </div>
        <div v-if=\"user.createdAt\">
          <dt class=\"text-sm font-medium text-gray-500\">Member Since</dt>
          <dd class=\"text-sm text-gray-900\">{{ formatDate(user.createdAt) }}</dd>
        </div>
        <div v-if=\"user.lastLoginAt\">
          <dt class=\"text-sm font-medium text-gray-500\">Last Login</dt>
          <dd class=\"text-sm text-gray-900\">{{ formatDate(user.lastLoginAt) }}</dd>
        </div>
      </dl>
    </div>
  </BaseCard>
</template>

<script setup lang=\"ts\">
import type { User } from '~/types/user'

interface Props {
  user: User
  editable?: boolean
  showDetails?: boolean
}

interface Emits {
  edit: [user: User]
}

const props = withDefaults(defineProps<Props>(), {
  editable: false,
  showDetails: false
})

const emit = defineEmits<Emits>()

const { formatDate } = useDateFormatter()

const userDisplayName = computed(() => {
  if (props.user.firstName && props.user.lastName) {
    return `${props.user.firstName} ${props.user.lastName}`
  }
  return props.user.email
})

const handleEdit = () => {
  emit('edit', props.user)
}
</script>
```

### UserList Component

```vue
<template>
  <div class=\"user-list\">
    <div class=\"flex items-center justify-between mb-6\">
      <h2 class=\"text-lg font-medium text-gray-900\">
        Users ({{ filteredUsers.length }})
      </h2>

      <div class=\"flex items-center space-x-4\">
        <BaseInput
          v-model=\"searchQuery\"
          placeholder=\"Search users...\"
          icon=\"search\"
          class=\"w-64\"
        />

        <BaseButton
          v-if=\"canCreate\"
          icon=\"plus\"
          @click=\"handleCreate\"
        >
          Add User
        </BaseButton>
      </div>
    </div>

    <div v-if=\"loading\" class=\"flex justify-center py-8\">
      <div class=\"animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600\"></div>
    </div>

    <div v-else-if=\"error\" class=\"text-center py-8\">
      <p class=\"text-red-600 mb-4\">{{ error }}</p>
      <BaseButton variant=\"outline\" @click=\"handleRetry\">
        Try Again
      </BaseButton>
    </div>

    <div v-else-if=\"filteredUsers.length === 0\" class=\"text-center py-8\">
      <Icon name=\"users\" class=\"w-12 h-12 text-gray-400 mx-auto mb-4\" />
      <p class=\"text-gray-500\">
        {{ searchQuery ? 'No users found matching your search.' : 'No users found.' }}
      </p>
    </div>

    <div v-else class=\"grid gap-4 md:grid-cols-2 lg:grid-cols-3\">
      <UserCard
        v-for=\"user in paginatedUsers\"
        :key=\"user.id\"
        :user=\"user\"
        :editable=\"canEdit\"
        @edit=\"handleEdit\"
      />
    </div>

    <div v-if=\"totalPages > 1\" class=\"mt-6 flex justify-center\">
      <nav class=\"flex items-center space-x-2\">
        <BaseButton
          variant=\"outline\"
          size=\"sm\"
          :disabled=\"currentPage === 1\"
          @click=\"currentPage--\"
        >
          Previous
        </BaseButton>

        <span class=\"px-3 py-1 text-sm text-gray-700\">
          Page {{ currentPage }} of {{ totalPages }}
        </span>

        <BaseButton
          variant=\"outline\"
          size=\"sm\"
          :disabled=\"currentPage === totalPages\"
          @click=\"currentPage++\"
        >
          Next
        </BaseButton>
      </nav>
    </div>
  </div>
</template>

<script setup lang=\"ts\">
import type { User } from '~/types/user'

interface Props {
  users: User[]
  loading?: boolean
  error?: string
  canCreate?: boolean
  canEdit?: boolean
}

interface Emits {
  create: []
  edit: [user: User]
  retry: []
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
  canCreate: true,
  canEdit: true
})

const emit = defineEmits<Emits>()

// Search and filtering
const searchQuery = ref('')
const currentPage = ref(1)
const itemsPerPage = 12

const filteredUsers = computed(() => {
  if (!searchQuery.value) return props.users

  const query = searchQuery.value.toLowerCase()
  return props.users.filter(user =>
    user.email.toLowerCase().includes(query) ||
    user.firstName?.toLowerCase().includes(query) ||
    user.lastName?.toLowerCase().includes(query)
  )
})

const totalPages = computed(() => Math.ceil(filteredUsers.value.length / itemsPerPage))

const paginatedUsers = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage
  const end = start + itemsPerPage
  return filteredUsers.value.slice(start, end)
})

// Reset pagination when search changes
watch(searchQuery, () => {
  currentPage.value = 1
})

const handleCreate = () => {
  emit('create')
}

const handleEdit = (user: User) => {
  emit('edit', user)
}

const handleRetry = () => {
  emit('retry')
}
</script>
```

## 📝 Form Components

Form components handle user input with validation and accessibility features.

### FormField Component

```vue
<template>
  <div class=\"form-field\">
    <BaseInput
      :model-value=\"modelValue\"
      :label=\"label\"
      :placeholder=\"placeholder\"
      :type=\"type\"
      :disabled=\"disabled\"
      :readonly=\"readonly\"
      :required=\"required\"
      :error=\"errorMessage\"
      :hint=\"hint\"
      :icon=\"icon\"
      @update:model-value=\"handleUpdate\"
      @blur=\"handleBlur\"
    />
  </div>
</template>

<script setup lang=\"ts\">
import { useField } from 'vee-validate'

interface Props {
  name: string
  label?: string
  placeholder?: string
  type?: 'text' | 'email' | 'password' | 'number' | 'tel' | 'url'
  disabled?: boolean
  readonly?: boolean
  required?: boolean
  hint?: string
  icon?: string
  rules?: any
  modelValue?: string | number
}

interface Emits {
  'update:modelValue': [value: string | number]
}

const props = withDefaults(defineProps<Props>(), {
  type: 'text',
  disabled: false,
  readonly: false,
  required: false
})

const emit = defineEmits<Emits>()

// Use vee-validate for validation
const {
  value: fieldValue,
  errorMessage,
  handleBlur,
  handleChange
} = useField(props.name, props.rules, {
  initialValue: props.modelValue
})

const handleUpdate = (value: string | number) => {
  fieldValue.value = value
  handleChange(value)
  emit('update:modelValue', value)
}

// Sync with external modelValue changes
watch(() => props.modelValue, (newValue) => {
  if (newValue !== fieldValue.value) {
    fieldValue.value = newValue
  }
})
</script>
```

## 🎨 Component Composition Patterns

### Composable Integration

```vue
<script setup lang=\"ts\">
// Use composables for business logic
const { users, loading, error, fetchUsers, createUser } = useUsers()
const { can } = usePermissions()
const { formatDate, formatCurrency } = useFormatters()

// Component-specific reactive state
const searchQuery = ref('')
const selectedUsers = ref<User[]>([])

// Computed properties for derived state
const filteredUsers = computed(() => {
  return users.value.filter(user =>
    user.email.includes(searchQuery.value)
  )
})

// Lifecycle hooks
onMounted(async () => {
  await fetchUsers()
})
</script>
```

### Event Handling Patterns

```vue
<template>
  <UserCard
    v-for=\"user in users\"
    :key=\"user.id\"
    :user=\"user\"
    @edit=\"handleUserEdit\"
    @delete=\"handleUserDelete\"
    @toggle-status=\"handleStatusToggle\"
  />
</template>

<script setup lang=\"ts\">
// Specific event handlers
const handleUserEdit = async (user: User) => {
  // Navigate to edit page or open modal
  await navigateTo(`/users/${user.id}/edit`)
}

const handleUserDelete = async (user: User) => {
  if (confirm('Are you sure you want to delete this user?')) {
    await deleteUser(user.id)
    await fetchUsers() // Refresh list
  }
}

const handleStatusToggle = async (user: User) => {
  await updateUser(user.id, {
    isActive: !user.isActive
  })
}
</script>
```

## 🧪 Component Testing

### Unit Testing Components

```typescript
// tests/components/UserCard.test.ts
import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import UserCard from '~/components/UserCard.vue'
import type { User } from '~/types/user'

describe('UserCard', () => {
  const mockUser: User = {
    id: '1',
    email: 'john@example.com',
    firstName: 'John',
    lastName: 'Doe',
  }

  it('renders user information correctly', () => {
    const wrapper = mount(UserCard, {
      props: { user: mockUser },
    })

    expect(wrapper.text()).toContain('John Doe')
    expect(wrapper.text()).toContain('john@example.com')
  })

  it('emits edit event when edit button is clicked', async () => {
    const wrapper = mount(UserCard, {
      props: { user: mockUser, editable: true },
    })

    await wrapper.find('[data-testid=\"edit-button\"]').trigger('click')

    expect(wrapper.emitted().edit).toBeTruthy()
    expect(wrapper.emitted().edit[0]).toEqual([mockUser])
  })
})
```

## 📚 Component Documentation

### Props Documentation

```vue
<script setup lang=\"ts\">
/**
 * UserCard - Displays user information in a card format
 *
 * @component
 * @example
 * <UserCard
 *   :user=\"user\"
 *   :editable=\"true\"
 *   :show-details=\"false\"
 *   @edit=\"handleEdit\"
 * />
 */

interface Props {
  /** User object containing user information */
  user: User
  /** Whether the card should show edit functionality */
  editable?: boolean
  /** Whether to show detailed user information */
  showDetails?: boolean
}
</script>
```

### Storybook Integration

```typescript
// stories/UserCard.stories.ts
import type { Meta, StoryObj } from '@storybook/vue3'
import UserCard from '~/components/UserCard.vue'

const meta: Meta<typeof UserCard> = {
  title: 'Components/UserCard',
  component: UserCard,
  parameters: {
    docs: {
      description: {
        component: 'A card component for displaying user information',
      },
    },
  },
  argTypes: {
    editable: {
      control: 'boolean',
      description: 'Whether the card allows editing',
    },
    showDetails: {
      control: 'boolean',
      description: 'Whether to show detailed information',
    },
  },
}

export default meta
type Story = StoryObj<typeof UserCard>

export const Default: Story = {
  args: {
    user: {
      id: '1',
      email: 'john@example.com',
      firstName: 'John',
      lastName: 'Doe',
    },
  },
}

export const Editable: Story = {
  args: {
    ...Default.args,
    editable: true,
  },
}

export const WithDetails: Story = {
  args: {
    ...Default.args,
    showDetails: true,
  },
}
```

## 🎯 Best Practices

### Performance Optimization

1. **Use v-show vs v-if appropriately**
2. **Implement virtual scrolling for large lists**
3. **Use computed properties for expensive calculations**
4. **Lazy load heavy components**
5. **Minimize prop drilling with provide/inject**

### Accessibility

1. **Use semantic HTML elements**
2. **Provide proper ARIA labels**
3. **Ensure keyboard navigation works**
4. **Maintain proper color contrast**
5. **Test with screen readers**

### Maintainability

1. **Keep components focused and single-purpose**
2. **Use TypeScript for type safety**
3. **Write comprehensive tests**
4. **Document component APIs**
5. **Follow consistent naming conventions**

This component guide provides the foundation for building consistent, reusable, and maintainable Vue components in the Basil application.
