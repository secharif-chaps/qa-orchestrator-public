<template>
  <div class="">
    <div class="mb-8">
      <h1 class="text-3xl font-bold">{{ $t('help.title') }}</h1>
      <p class="text-secondary mt-2">{{ $t('help.description') }}</p>
    </div>

    <!-- No help content available -->
    <div v-if="!hasAnyHelpAccess" class="py-16 text-center">
      <div class="text-gray-500 dark:text-gray-400">
        <div class="mb-4 text-6xl">📚</div>
        <h3 class="mb-2 text-xl font-medium">
          {{ $t('help.noContent.title', 'No Help Content Available') }}
        </h3>
        <p>
          {{
            $t(
              'help.noContent.message',
              "You don't have access to any help sections based on your current permissions.",
            )
          }}
        </p>
      </div>
    </div>

    <div v-else class="lg:grid lg:grid-cols-4 lg:gap-8">
      <!-- Desktop Sidebar Navigation -->
      <div class="hidden lg:col-span-1 lg:block">
        <nav class="sticky top-8 space-y-1">
          <div v-for="category in helpCategories" :key="category" class="mb-4">
            <div class="text-secondary mb-2 text-xs font-semibold tracking-wider uppercase">
              {{ getCategoryTitle(category) }}
            </div>
            <div class="space-y-1">
              <button
                v-for="section in helpSectionsByCategory[category]"
                :key="section.permission"
                @click="selectedSection = section"
                class="group flex w-full items-center rounded-md px-3 py-2 text-left text-sm font-medium transition-colors"
                :class="
                  selectedSection?.permission === section.permission
                    ? 'bg-base-100 text-secondary border-primary'
                    : 'text-secondary hover:text-secondary hover:bg-base-200'
                "
              >
                <span class="truncate">{{ section.title }}</span>
              </button>
            </div>
          </div>
        </nav>
      </div>

      <!-- Mobile Navigation -->
      <div class="mb-6 lg:hidden">
        <select
          v-model="selectedSectionPermission"
          @change="onMobileSelectChange"
          class="border-primary-stroke bg-base-100 text-secondary focus:ring-primary w-full rounded-md border px-3 py-2 focus:ring-2 focus:outline-none"
        >
          <option value="">{{ $t('help.selectTopic.placeholder', 'Select a help topic') }}</option>
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
        <div v-if="!selectedSection" class="py-16 text-center">
          <div class="text-gray-500 dark:text-gray-400">
            <div class="mb-4 text-4xl">👈</div>
            <h3 class="mb-2 text-lg font-medium">
              {{ $t('help.selectTopic.title', 'Select a Help Topic') }}
            </h3>
            <p>
              {{
                $t(
                  'help.selectTopic.message',
                  'Choose a topic from the sidebar to view detailed documentation.',
                )
              }}
            </p>
          </div>
        </div>

        <div v-else class="bg-base-100 rounded-lg p-6">
          <div class="mb-6">
            <h2 class="mb-2 text-2xl font-bold text-gray-900 dark:text-gray-100">
              {{ selectedSection.title }}
            </h2>
            <p class="text-secondary">{{ selectedSection.description }}</p>
          </div>

          <div class="border-primary-stroke border-t pt-6">
            <div
              v-if="selectedSectionContent"
              class="prose prose-gray dark:prose-invert max-w-none space-y-4"
              v-sanitize-html="selectedSectionContent"
            ></div>
            <div v-else class="flex items-center justify-center py-8">
              <div class="text-center">
                <div
                  class="border-primary mx-auto mb-4 h-12 w-12 animate-spin rounded-full border-b-2"
                ></div>
                <p class="text-secondary">
                  {{ $t('help.loading.content', 'Loading help content...') }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { ref, watch } from 'vue'
import { marked } from 'marked'
import { usePermissionBasedHelp } from '@/composables/usePermissionBasedHelp'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const {
  hasAnyHelpAccess,
  helpCategories,
  helpSectionsByCategory,
  loadHelpContent,
  availableHelpSections,
} = usePermissionBasedHelp()

const selectedSection = ref<{ permission: string; title: string; description: string } | null>(null)
const selectedSectionContent = ref<string>('')
const selectedSectionPermission = ref<string>('')

// Category titles mapping
const getCategoryTitle = (category: string) => {
  const titles: Record<string, string> = {
    admin: t('help.categories.admin', 'Administration'),
    company: t('help.categories.company', 'Company Screening'),
    organization: t('help.categories.organization', 'organization Management'),
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
      selectedSectionContent.value = await marked(content)
    } catch (error) {
      console.error('Error loading help content:', error)
      selectedSectionContent.value = `<p>${t('help.error.loadingContent', 'Error loading help content.')}</p>`
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
  @apply mb-4 text-2xl font-bold;
}

.prose h2 {
  @apply mt-6 mb-3 text-xl font-semibold;
}

.prose h3 {
  @apply mt-4 mb-2 text-lg font-medium;
}

.prose h4 {
  @apply mt-3 mb-2 font-medium;
}

.prose ul,
.prose ol {
  @apply mb-4 ml-6;
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
  @apply rounded bg-gray-100 px-1 py-0.5 text-sm dark:bg-gray-700;
}

.prose pre {
  @apply mb-4 overflow-x-auto rounded bg-gray-100 p-4 dark:bg-gray-700;
}

.prose blockquote {
  @apply mb-4 border-l-4 border-gray-300 pl-4 italic dark:border-gray-600;
}

.prose strong {
  @apply font-semibold;
}
</style>
