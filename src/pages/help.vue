<template>
  <div class="">
    <div class="mb-8">
      <h1 class="text-3xl font-bold">{{ $t('help.title') }}</h1>
      <p class="text-secondary mt-2">{{ $t('help.description') }}</p>
    </div>

    <!-- No help content available -->
    <div v-if="!hasAnyHelpAccess" class="text-center py-16">
      <div class="text-gray-500 dark:text-gray-400">
        <div class="text-6xl mb-4">📚</div>
        <h3 class="text-xl font-medium mb-2">No Help Content Available</h3>
        <p>You don't have access to any help sections based on your current permissions.</p>
      </div>
    </div>

    <div v-else class="lg:grid lg:grid-cols-4 lg:gap-8">
      <!-- Desktop Sidebar Navigation -->
      <div class="hidden lg:block lg:col-span-1">
        <nav class="space-y-1 sticky top-8">
          <div v-for="category in helpCategories" :key="category" class="mb-4">
            <div class="text-xs font-semibold text-secondary uppercase tracking-wider mb-2">
              {{ getCategoryTitle(category) }}
            </div>
            <div class="space-y-1">
              <button
                v-for="section in helpSectionsByCategory[category]"
                :key="section.permission"
                @click="selectedSection = section"
                class="w-full group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors text-left"
                :class="
                  selectedSection?.permission === section.permission
                    ? 'bg-bg1 text-secondary border-primary'
                    : 'text-secondary hover:text-primary hover:bg-bg2'
                "
              >
                <span class="truncate">{{ section.title }}</span>
              </button>
            </div>
          </div>
        </nav>
      </div>

      <!-- Mobile Navigation -->
      <div class="lg:hidden mb-6">
        <select
          v-model="selectedSectionPermission"
          @change="onMobileSelectChange"
          class="w-full px-3 py-2 border border-border-2 rounded-md bg-bg1 text-secondary focus:outline-none focus:ring-2 focus:ring-primary"
        >
          <option value="">Select a help topic</option>
          <optgroup
            v-for="category in helpCategories"
            :key="category"
            :label="getCategoryTitle(category)"
          >
            <option
              v-for="section in helpSectionsByCategory[category]"
              :key="section.permission"
              :value="section.permission"
            >
              {{ section.title }}
            </option>
          </optgroup>
        </select>
      </div>

      <!-- Main Content -->
      <div class="lg:col-span-3">
        <div v-if="!selectedSection" class="text-center py-16">
          <div class="text-gray-500 dark:text-gray-400">
            <div class="text-4xl mb-4">👈</div>
            <h3 class="text-lg font-medium mb-2">Select a Help Topic</h3>
            <p>Choose a topic from the sidebar to view detailed documentation.</p>
          </div>
        </div>

        <div v-else class="bg-bg1 rounded-lg p-6">
          <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">
              {{ selectedSection.title }}
            </h2>
            <p class="text-secondary">{{ selectedSection.description }}</p>
          </div>

          <div class="border-t border-border-2 pt-6">
            <div
              v-if="selectedSectionContent"
              class="prose prose-gray dark:prose-invert max-w-none space-y-4"
              v-html="selectedSectionContent"
            ></div>
            <div v-else class="flex items-center justify-center py-8">
              <div class="text-center">
                <div
                  class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"
                ></div>
                <p class="text-secondary">Loading help content...</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { ref, watch, computed } from 'vue'
import { marked } from 'marked'
import { usePermissionBasedHelp } from '@/composables/usePermissionBasedHelp'

const {
  hasAnyHelpAccess,
  helpCategories,
  helpSectionsByCategory,
  loadHelpContent,
  availableHelpSections,
} = usePermissionBasedHelp()

const selectedSection = ref<any>(null)
const selectedSectionContent = ref<string>('')
const selectedSectionPermission = ref<string>('')

// Category titles mapping
const getCategoryTitle = (category: string) => {
  const titles: Record<string, string> = {
    admin: 'Administration',
    company: 'Company Screening',
    workspace: 'Workspace Management',
  }
  return titles[category] || category
}

// Handle mobile select change
const onMobileSelectChange = () => {
  if (selectedSectionPermission.value) {
    const section = availableHelpSections.value.find(
      (s) => s.permission === selectedSectionPermission.value,
    )
    if (section) {
      selectedSection.value = section
    }
  }
}

// Watch for selected section changes and load content
watch(selectedSection, async (newSection) => {
  if (newSection) {
    selectedSectionContent.value = ''
    selectedSectionPermission.value = newSection.permission
    try {
      const content = await loadHelpContent(newSection.permission)
      selectedSectionContent.value = marked(content)
    } catch (error) {
      console.error('Error loading help content:', error)
      selectedSectionContent.value = '<p>Error loading help content.</p>'
    }
  } else {
    selectedSectionContent.value = ''
    selectedSectionPermission.value = ''
  }
})

// Auto-select first item if available on mount
if (availableHelpSections.value.length > 0 && !selectedSection.value) {
  selectedSection.value = availableHelpSections.value[0]
}
</script>

<style>
@reference "tailwindcss";
/* Custom styles for the help page */
.prose h1 {
  @apply text-2xl font-bold mb-4;
}

.prose h2 {
  @apply text-xl font-semibold mb-3 mt-6;
}

.prose h3 {
  @apply text-lg font-medium mb-2 mt-4;
}

.prose h4 {
  @apply font-medium mb-2 mt-3;
}

.prose ul,
.prose ol {
  @apply ml-6 mb-4;
}

.prose ul {
  @apply list-disc;
}

.prose ol {
  @apply list-decimal;
}

.prose li {
  @apply mb-1;
}

.prose p {
  @apply mb-4;
}

.prose code {
  @apply bg-gray-100 dark:bg-gray-700 px-1 py-0.5 rounded text-sm;
}

.prose pre {
  @apply bg-gray-100 dark:bg-gray-700 p-4 rounded overflow-x-auto mb-4;
}

.prose blockquote {
  @apply border-l-4 border-gray-300 dark:border-gray-600 pl-4 italic mb-4;
}

.prose strong {
  @apply font-semibold;
}
</style>
