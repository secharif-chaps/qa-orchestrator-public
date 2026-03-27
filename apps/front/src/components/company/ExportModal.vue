<template>
  <Modal
    v-model:display-modal="isOpen"
    :title="t('screen.company.export.modal.title')"
    size="xl"
    icon="fas fa-download"
    color="sage"
  >
    <template #description>
      {{ t('screen.company.export.modal.description') }}
    </template>

    <div class="flex flex-col gap-4">
      <!-- Select All / None toggle -->
      <div class="flex items-center justify-between">
        <span v-if="showSavedMessage" class="text-secondary animate-fade-out text-xs">
          <i class="fa fa-check-circle mr-1"></i
          >{{ t('screen.company.export.modal.preferencesSaved') }}
        </span>
        <Button
          variant="tertiary"
          :label="
            allSelected
              ? t('screen.company.export.modal.deselectAll')
              : t('screen.company.export.modal.selectAll')
          "
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
          <div class="mr-4 flex-1">
            <label class="text-sm font-medium">{{ option.label }}</label>
            <p v-if="option.description" class="text-secondary mt-1 text-xs">
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
      <Button variant="secondary" :label="t('screen.company.export.modal.cancel')" @click="close" />
      <Button
        variant="primary"
        :label="t('screen.company.export.modal.export')"
        icon="fa fa-download"
        @click="exportPPT"
      />
    </template>
  </Modal>
</template>

<script lang="ts" setup>
import { Button, Modal, Switch } from '@owlint/feathers-vue'
import type { Company } from '@/types/company'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

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

// Option IDs for export
const optionIds = [
  'titleSlide',
  'profile',
  'productsServices',
  'targetAudience',
  'digitalStrategy',
  'csr',
  'news',
  'timeline',
  'team',
  'jobs',
  'press',
] as const

// Helper to get translated option
const getOptionLabel = (id: string) => t(`screen.company.export.modal.options.${id}.label`)
const getOptionDescription = (id: string) =>
  t(`screen.company.export.modal.options.${id}.description`)

// Default export options with dynamic labels
const createDefaultOptions = () =>
  optionIds.map((id) => ({
    id,
    get label() {
      return getOptionLabel(id)
    },
    get description() {
      return getOptionDescription(id)
    },
    selected: true,
  }))

// Define export options with checkboxes (all checked by default)
const exportOptions = ref(createDefaultOptions())
const showSavedMessage = ref(false)
const preferencesChanged = ref(false)

// Load saved preferences from localStorage
onMounted(() => {
  const savedOptions = localStorage.getItem('exportPreferences')
  if (savedOptions) {
    try {
      const parsedOptions = JSON.parse(savedOptions)
      // Merge saved selections with default options to ensure we have all options
      exportOptions.value = optionIds.map((id) => {
        const savedOpt = parsedOptions.find((opt: { id: string }) => opt.id === id)
        return {
          id,
          get label() {
            return getOptionLabel(id)
          },
          get description() {
            return getOptionDescription(id)
          },
          selected: savedOpt ? savedOpt.selected : true,
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
