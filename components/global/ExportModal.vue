<template>
  <div v-if="isOpen" class="fixed inset-0 flex items-center justify-center z-50">
    <div class="fixed inset-0 bg-black opacity-50" @click="close"></div>
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 z-10">
      <div class="p-6">
        <div class="flex justify-between items-center border-b pb-3 border-border-2">
          <h3 class="text-lg font-semibold text-primary">Export Options</h3>
          <button @click="close" class="text-slate-500 hover:text-slate-700">
            <i class="fa fa-times"></i>
          </button>
        </div>
        
        <div class="py-4 space-y-3">
          <p class="text-sm text-slate-600">Select which sections to include in your PowerPoint export:</p>
          
          <!-- Select All / None toggle -->
          <div class="flex justify-between mb-2">
            <span v-if="showSavedMessage" class="text-xs text-primary animate-fade-out">
              <i class="fa fa-check-circle mr-1"></i>Preferences saved
            </span>
            <button 
              @click="toggleAll" 
              class="text-xs text-primary hover:text-primary-dark"
            >
              {{ allSelected ? 'Deselect All' : 'Select All' }}
            </button>
          </div>
          
          <div class="space-y-2 mt-3">
            <div v-for="(option, index) in exportOptions" :key="index" class="flex items-start">
              <div class="flex items-center h-5">
                <input
                  :id="'option-' + index"
                  v-model="option.selected"
                  type="checkbox"
                  class="h-4 w-4 text-primary border-gray-300 rounded"
                  @change="preferencesChanged = true"
                />
              </div>
              <div class="ml-3 text-sm">
                <label :for="'option-' + index" class="font-medium text-gray-700">{{ option.label }}</label>
                <p v-if="option.description" class="text-gray-500">{{ option.description }}</p>
              </div>
            </div>
          </div>
        </div>
        
        <div class="mt-5 border-t pt-4 border-border-2 flex justify-between">
          <OButton type="secondary" @click="close">Cancel</OButton>
          <OButton type="primary" @click="exportPPT">Export</OButton>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { OButton } from '@owlint/feathers-vue'
import type { Company, SourcedValue } from '@/types'
import { onMounted } from 'vue'

const props = defineProps<{
  isOpen: boolean
  company: Company | null
}>()

const emit = defineEmits(['close', 'export'])

// Default export options
const defaultOptions = [
  { 
    id: 'titleSlide', 
    label: 'Title Slide', 
    description: 'Cover page with company name and date',
    selected: true 
  },
  { 
    id: 'insights', 
    label: 'Company Insights', 
    description: 'Key insights and analysis about the company',
    selected: true 
  },
  { 
    id: 'profile', 
    label: 'Company Profile', 
    description: 'Basic information, business lines, and key metrics',
    selected: true 
  },
  { 
    id: 'productsServices', 
    label: 'Products and Services', 
    description: 'Product range, partner brands, and private labels',
    selected: true 
  },
  { 
    id: 'targetAudience', 
    label: 'Target Audience & Customer Base', 
    description: 'Customer type and marketing positioning',
    selected: true 
  },
  { 
    id: 'digitalStrategy', 
    label: 'Digital Strategy & Social Media', 
    description: 'Digital approach, loyalty programs, and online services',
    selected: true 
  },
  { 
    id: 'csr', 
    label: 'Corporate Social Responsibility', 
    description: 'Responsibility initiatives and charity actions',
    selected: true 
  },
  { 
    id: 'news', 
    label: 'Recent News', 
    description: 'Latest company updates and announcements',
    selected: true 
  }
]

// Define export options with checkboxes (all checked by default)
const exportOptions = ref([...defaultOptions])
const showSavedMessage = ref(false)
const preferencesChanged = ref(false)

// Load saved preferences from localStorage
onMounted(() => {
  if (process.client) {
    const savedOptions = localStorage.getItem('exportPreferences')
    if (savedOptions) {
      try {
        const parsedOptions = JSON.parse(savedOptions)
        // Merge saved selections with default options to ensure we have all options
        exportOptions.value = defaultOptions.map(defaultOpt => {
          const savedOpt = parsedOptions.find(opt => opt.id === defaultOpt.id)
          return {
            ...defaultOpt,
            selected: savedOpt ? savedOpt.selected : defaultOpt.selected
          }
        })
      } catch (e) {
        console.error('Error loading export preferences:', e)
      }
    }
  }
})

// Function to save preferences
const savePreferences = () => {
  if (process.client) {
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
}

// Computed property to check if all options are selected
const allSelected = computed(() => {
  return exportOptions.value.every(option => option.selected)
})

// Function to toggle all options
const toggleAll = () => {
  const newValue = !allSelected.value
  exportOptions.value.forEach(option => {
    option.selected = newValue
  })
  preferencesChanged.value = true
}

const close = () => {
  emit('close')
}

const exportPPT = () => {
  const selectedOptions = exportOptions.value
    .filter(option => option.selected)
    .map(option => option.id)
  
  // Save preferences before export
  savePreferences()
  
  emit('export', selectedOptions)
}
</script>

<style scoped>
.animate-fade-out {
  animation: fadeOut 2s ease-in-out;
}

@keyframes fadeOut {
  0% { opacity: 1; }
  70% { opacity: 1; }
  100% { opacity: 0; }
}
</style> 