# Component Notes & Requirements

## 🎨 @owlint/feathers-vue Components

### OInput Component
- **MANDATORY**: `id` prop is required for all OInput components
- **Accessibility**: The id prop is used for proper form labeling and accessibility
- **Example**:
  ```vue
  <OInput
    id="email"
    v-model="form.email"
    :label="$t('form.email')"
    type="email"
    required
  />
  ```

### OButton Component
- **Loading state**: Use `:loading="saving"` for async operations
- **Types**: `primary`, `secondary`, `success`, `danger`, `warning`
- **Colors**: `blue`, `green`, `red`, `yellow`, `gray`
- **Example**:
  ```vue
  <OButton
    :label="$t('actions.save')"
    type="primary"
    color="blue"
    :loading="saving"
    @click="handleSave"
  />
  ```

### OModal Component
- **v-model**: Controls modal visibility
- **Title**: Pass title as prop
- **Example**:
  ```vue
  <OModal
    v-model="showModal"
    :title="$t('modal.title')"
  >
    <form @submit.prevent="handleSubmit">
      <!-- Modal content -->
    </form>
  </OModal>
  ```

### OTable Component
- **Fields**: Define table columns structure
- **Items**: Data array to display
- **Pagination**: Built-in pagination support
- **Example**:
  ```vue
  <OTable
    :fields="tableFields"
    :items="tableData"
    :pagination="paginationConfig"
  />
  ```

## 🎯 Component Selection Priority

1. **@owlint/feathers-vue** (First choice - pre-styled)
2. **Reka UI** (Second choice - headless with Tailwind)
3. **Custom components** (Last resort)

## 🚨 Common Mistakes to Avoid

### OInput Without ID
```vue
<!-- ❌ WRONG: Missing required id prop -->
<OInput v-model="name" label="Name" />

<!-- ✅ CORRECT: Include id prop -->
<OInput id="name" v-model="name" label="Name" />
```

### Manual v-model Implementation
```vue
<!-- ❌ WRONG: Manual props + emit -->
<script setup>
const props = defineProps(['modelValue'])
const emit = defineEmits(['update:modelValue'])
</script>

<!-- ✅ CORRECT: Use defineModel -->
<script setup>
const modelValue = defineModel<string>()
</script>
```

### Importing Icons
```vue
<!-- ❌ WRONG: Importing icon libraries -->
<script setup>
import { ChevronLeft } from 'lucide-vue-next'
</script>

<!-- ✅ CORRECT: Use Font Awesome classes -->
<i class="fas fa-chevron-left"></i>
```

## 📝 Form Best Practices

### Complete Form Example
```vue
<script setup lang="ts">
import { OInput, OCheckbox, OButton } from '@owlint/feathers-vue'

const form = reactive({
  name: '',
  email: '',
  terms: false
})

const errors = reactive({})
const saving = ref(false)

const handleSubmit = async () => {
  saving.value = true
  try {
    // Submit logic
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <form @submit.prevent="handleSubmit" class="space-y-4">
    <OInput
      id="name"
      v-model="form.name"
      :label="$t('form.name')"
      :error="errors.name"
      required
    />
    
    <OInput
      id="email"
      v-model="form.email"
      :label="$t('form.email')"
      type="email"
      :error="errors.email"
      required
    />
    
    <OCheckbox
      v-model="form.terms"
      :label="$t('form.terms')"
    />
    
    <OButton
      :label="$t('actions.submit')"
      type="primary"
      :loading="saving"
      @click="handleSubmit"
    />
  </form>
</template>
```

---

*Component-specific guidelines and requirements*
*Last updated: 2025-07-31*