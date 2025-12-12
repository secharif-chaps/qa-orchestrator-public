<template>
  <Modal
    v-model:display-modal="isOpen"
    title="Export Options"
    size="xl"
    icon="fas fa-download"
    color="sage"
  >
    <template #description>
      Select which sections to include in your PowerPoint export:
    </template>

    <div class="flex flex-col gap-4">
      <!-- Select All / None toggle -->
      <div class="flex justify-between items-center">
        <span v-if="showSavedMessage" class="text-xs text-secondary animate-fade-out">
          <i class="fa fa-check-circle mr-1"></i>Preferences saved
        </span>
        <Button
          variant="tertiary"
          :label="allSelected ? 'Deselect All' : 'Select All'"
          size="sm"
          @click="toggleAll"
        />
      </div>

      <div class="flex flex-col gap-4">
        <div
          v-for="(option, index) in exportOptions"
          :key="index"
          class="flex items-center justify-between"
        >
          <div class="flex-1 mr-4">
            <label class="font-medium text-sm">{{ option.label }}</label>
            <p v-if="option.description" class="text-secondary text-xs mt-1">
              {{ option.description }}
            </p>
          </div>

          <Switch
            :id="`export-option-${index}`"
            v-model="option.selected"
            @update:model-value="preferencesChanged = true"
          />
        </div>
      </div>
    </div>

    <template #footer>
      <Button variant="secondary" label="Cancel" @click="close" />
      <Button variant="primary" label="Export" icon="fa fa-download" @click="exportPPT" />
    </template>
  </Modal>
</template>

<script lang="ts" setup>
import { Button, Modal, Switch } from '@owlint/feathers-vue'
import type { Company } from '@/types/company'
import { computed, onMounted, ref, watch } from 'vue'

const props = defineProps<{
  isOpen: boolean
  company: Company | null
}>()

// Create a local reactive reference for the modal state
const isOpen = ref(props.isOpen)

// Watch for changes in the prop and update local state
watch(
  () => props.isOpen,
  (newValue) => {
    isOpen.value = newValue
  },
)

// Watch for changes in local state and emit close event
watch(isOpen, (newValue) => {
  if (!newValue && props.isOpen) {
    emit('close')
  }
})

const emit = defineEmits(['close', 'export'])

// Default export options
const defaultOptions = [
  {
    id: 'titleSlide',
    label: 'Title Slide',
    description: 'Cover page with company name and date',
    selected: true,
  },
  {
    id: 'profile',
    label: 'Company Profile',
    description: 'Basic information, business lines, and key metrics',
    selected: true,
  },
  {
    id: 'productsServices',
    label: 'Products and Services',
    description: 'Product range, partner brands, and private labels',
    selected: true,
  },
  {
    id: 'targetAudience',
    label: 'Target Audience & Customer Base',
    description: 'Customer type and marketing positioning',
    selected: true,
  },
  {
    id: 'digitalStrategy',
    label: 'Digital Strategy & Social Media',
    description: 'Digital approach, loyalty programs, and online services',
    selected: true,
  },
  {
    id: 'csr',
    label: 'Corporate Social Responsibility',
    description: 'Responsibility initiatives and charity actions',
    selected: true,
  },
  {
    id: 'news',
    label: 'Press & Media',
    description: 'Press articles and media coverage',
    selected: true,
  },
  {
    id: 'timeline',
    label: 'Timeline',
    description: 'Company timeline events and milestones',
    selected: true,
  },
  {
    id: 'team',
    label: 'Team & Management',
    description: 'Leadership team and organizational structure',
    selected: true,
  },
  {
    id: 'jobs',
    label: 'Job Opportunities',
    description: 'Current job openings and hiring information',
    selected: true,
  },
  {
    id: 'press',
    label: 'Press Coverage',
    description: 'Media articles and press releases',
    selected: true,
  },
]

// Define export options with checkboxes (all checked by default)
const exportOptions = ref([...defaultOptions])
const showSavedMessage = ref(false)
const preferencesChanged = ref(false)

// Load saved preferences from localStorage
onMounted(() => {
  const savedOptions = localStorage.getItem('exportPreferences')
  if (savedOptions) {
    try {
      const parsedOptions = JSON.parse(savedOptions)
      // Merge saved selections with default options to ensure we have all options
      exportOptions.value = defaultOptions.map((defaultOpt) => {
        const savedOpt = parsedOptions.find((opt) => opt.id === defaultOpt.id)
        return {
          ...defaultOpt,
          selected: savedOpt ? savedOpt.selected : defaultOpt.selected,
        }
      })
    } catch (e) {
      console.error('Error loading export preferences:', e)
    }
  }
})

// Function to save preferences
const savePreferences = () => {
  try {
    localStorage.setItem('exportPreferences', JSON.stringify(exportOptions.value))

    // Only show saved message if preferences have changed
    if (preferencesChanged.value) {
      showSavedMessage.value = true
      setTimeout(() => {
        showSavedMessage.value = false
      }, 2000)
      preferencesChanged.value = false
    }
  } catch (e) {
    console.error('Error saving export preferences:', e)
  }
}

// Computed property to check if all options are selected
const allSelected = computed(() => {
  return exportOptions.value.every((option) => option.selected)
})

// Function to toggle all options
const toggleAll = () => {
  const newValue = !allSelected.value
  exportOptions.value.forEach((option) => {
    option.selected = newValue
  })
  preferencesChanged.value = true
}

const close = () => {
  emit('close')
}

const exportPPT = () => {
  console.log('🔧 Export modal: All export options:', exportOptions.value)

  const selectedOptions = exportOptions.value
    .filter((option) => option.selected)
    .map((option) => option.id)

  console.log('✅ Export modal: Selected options:', selectedOptions)
  console.log('📊 Export modal: Number of selected options:', selectedOptions.length)

  // Save preferences before export
  savePreferences()

  console.log('📤 Export modal: Emitting export event with options:', selectedOptions)
  emit('export', selectedOptions)
}
</script>

<style scoped>
.animate-fade-out {
  animation: fadeOut 2s ease-in-out;
}

@keyframes fadeOut {
  0% {
    opacity: 1;
  }
  70% {
    opacity: 1;
  }
  100% {
    opacity: 0;
  }
}
</style>
