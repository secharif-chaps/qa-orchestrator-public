<template>
  <div class="bg-base-100 rounded-lg p-12 text-center">
    <!-- Loading State -->
    <div v-if="state === 'loading'" class="animate-pulse">
      <div class="flex justify-center mb-4">
        <i :class="loadingIcon" class="text-4xl text-primary-light-content animate-spin"></i>
      </div>
      <h3 class="text-xl font-semibold text-primary-light-content mb-2">
        {{ loadingTitle }}
      </h3>
      <p class="text-primary-light-content max-w-md mx-auto">
        {{ loadingDescription }}
      </p>
      <!-- Progress indicator for multiple tasks -->
      <div v-if="taskProgress && taskProgress.total > 1" class="mt-6">
        <div class="bg-base-200 rounded-full h-2 max-w-xs mx-auto">
          <div
            class="bg-primary rounded-full h-2 transition-all duration-300"
            :style="{ width: `${(taskProgress.completed / taskProgress.total) * 100}%` }"
          ></div>
        </div>
        <p class="text-xs text-primary-light-content mt-2">
          {{ taskProgress.completed }} of {{ taskProgress.total }} sections loaded
        </p>
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="state === 'error'" class="flex flex-col items-center">
      <!-- Chapse Error Image -->
      <div class="mb-6">
        <img :src="chapseErrorImage" alt="Error" class="w-24 h-auto" />
      </div>

      <!-- Error Title -->
      <h3 class="text-xl font-semibold text-primary-light-content mb-2">
        {{ errorTitle }}
      </h3>

      <!-- Error Description -->
      <p class="text-primary-light-content max-w-md mx-auto mb-4 text-center">
        {{ errorDescription }}
      </p>

      <!-- Error details -->
      <div
        v-if="errorMessage"
        class="bg-error-500/10 border border-error-500/20 rounded-lg p-4 max-w-md mx-auto mb-6"
      >
        <p class="text-sm text-error-500">{{ errorMessage }}</p>
      </div>

      <!-- Retry action -->
      <Button
        v-if="showRetryButton"
        variant="primary"
        icon="fa fa-refresh"
        :label="retryButtonLabel"
        @click="$emit('retry')"
      />
    </div>

    <!-- No Data State -->
    <div v-else-if="state === 'no-data'">
      <div class="flex justify-center mb-4">
        <i :class="noDataIcon" class="text-4xl text-primary-light-content/30"></i>
      </div>
      <h3 class="text-xl font-semibold text-primary-light-content mb-2">
        {{ noDataTitle }}
      </h3>
      <p class="text-primary-light-content max-w-md mx-auto mb-6">
        {{ noDataDescription }}
      </p>
      <!-- Custom action button -->
      <Button
        v-if="showActionButton"
        :variant="actionButton.variant || 'primary'"
        :icon="actionButton.icon"
        :label="actionButton.label"
        @click="$emit('action', actionButton.action)"
      />
    </div>

    <!-- No Results State (for search/filter) -->
    <div v-else-if="state === 'no-results'">
      <div class="flex justify-center mb-4">
        <i class="fa fa-search text-4xl text-primary-light-content/30"></i>
      </div>
      <h3 class="text-xl font-semibold text-primary-light-content mb-2">No results found</h3>
      <p class="text-primary-light-content max-w-md mx-auto mb-6">
        <span v-if="searchQuery">
          We couldn't find anything matching "<strong>{{ searchQuery }}</strong
          >".
        </span>
        <span v-else> Try adjusting your search criteria or filters. </span>
      </p>
      <Button
        variant="secondary"
        icon="fa fa-times"
        label="Clear Search"
        @click="$emit('clear-search')"
      />
    </div>
  </div>
</template>

<script lang="ts" setup>
import { computed } from 'vue'
import Button from '@/components/ui/Button.vue'
import type { TaskType } from '@/types/task'
import chapseErrorImage from '@/assets/chapse/error_light.svg'

interface Props {
  state: 'loading' | 'error' | 'no-data' | 'no-results'
  pageType?: TaskType | TaskType[]
  searchQuery?: string
  errorMessage?: string
  taskProgress?: {
    completed: number
    total: number
  }
  // Customization props
  loadingTitle?: string
  loadingDescription?: string
  loadingIcon?: string
  errorTitle?: string
  errorDescription?: string
  errorIcon?: string
  noDataTitle?: string
  noDataDescription?: string
  noDataIcon?: string
  showRetryButton?: boolean
  retryButtonLabel?: string
  showActionButton?: boolean
  actionButton?: {
    variant?: 'primary' | 'secondary' | 'tertiary'
    icon?: string
    label: string
    action?: string
  }
}

const props = withDefaults(defineProps<Props>(), {
  loadingIcon: 'fa fa-spinner',
  errorIcon: 'fa fa-exclamation-triangle',
  showRetryButton: true,
  retryButtonLabel: 'Try Again',
  showActionButton: false,
})

defineEmits<{
  retry: []
  action: [action?: string]
  'clear-search': []
}>()

// Default content based on page type
const pageTypeArray = computed(() => {
  if (!props.pageType) return []
  return Array.isArray(props.pageType) ? props.pageType : [props.pageType]
})

const primaryPageType = computed(() => pageTypeArray.value[0] || 'profile')

// Page type configurations
const pageConfigs = {
  profile: {
    loading: {
      title: 'Analyzing Company Profile',
      description: 'Our AI is gathering comprehensive information about this company...',
      icon: 'fa fa-building',
    },
    error: {
      title: 'Profile Analysis Failed',
      description: 'We encountered an issue while analyzing the company profile.',
      icon: 'fa fa-exclamation-triangle',
    },
    noData: {
      title: 'No Profile Data Available',
      description: "We couldn't find detailed profile information for this company.",
      icon: 'fa fa-building',
    },
  },
  timeline: {
    loading: {
      title: 'Building Company Timeline',
      description: 'Collecting key events and milestones from company history...',
      icon: 'fa fa-clock',
    },
    error: {
      title: 'Timeline Loading Failed',
      description: "We couldn't load the company timeline at this time.",
      icon: 'fa fa-exclamation-triangle',
    },
    noData: {
      title: 'No Timeline Events',
      description: "We haven't found any significant events in this company's history yet.",
      icon: 'fa fa-clock',
    },
  },
  jobs: {
    loading: {
      title: 'Scanning Job Opportunities',
      description: 'Discovering current job openings and career opportunities...',
      icon: 'fa fa-briefcase',
    },
    error: {
      title: 'Jobs Loading Failed',
      description: "We couldn't fetch current job listings.",
      icon: 'fa fa-exclamation-triangle',
    },
    noData: {
      title: 'No Job Openings Found',
      description: "This company doesn't have any public job listings at the moment.",
      icon: 'fa fa-briefcase',
    },
  },
  team: {
    loading: {
      title: 'Mapping Team Structure',
      description: 'Identifying key team members and organizational hierarchy...',
      icon: 'fa fa-users',
    },
    error: {
      title: 'Team Data Loading Failed',
      description: "We couldn't load the team information.",
      icon: 'fa fa-exclamation-triangle',
    },
    noData: {
      title: 'No Team Information',
      description: "We haven't found public information about this company's team yet.",
      icon: 'fa fa-users',
    },
  },
  products: {
    loading: {
      title: 'Cataloging Products & Services',
      description: "Discovering the company's product portfolio and offerings...",
      icon: 'fa fa-box',
    },
    error: {
      title: 'Products Loading Failed',
      description: "We couldn't load the product information.",
      icon: 'fa fa-exclamation-triangle',
    },
    noData: {
      title: 'No Products Found',
      description: "We haven't identified any specific products or services for this company.",
      icon: 'fa fa-box',
    },
  },
  press: {
    loading: {
      title: 'Gathering Press Coverage',
      description: 'Collecting recent news articles and media mentions...',
      icon: 'fa fa-newspaper',
    },
    error: {
      title: 'Press Coverage Loading Failed',
      description: "We couldn't fetch recent press coverage.",
      icon: 'fa fa-exclamation-triangle',
    },
    noData: {
      title: 'No Press Coverage Found',
      description:
        "We haven't found any recent press coverage or news articles about this company.",
      icon: 'fa fa-newspaper',
    },
  },
  csr: {
    loading: {
      title: 'Analyzing CSR Initiatives',
      description: 'Researching corporate social responsibility and sustainability efforts...',
      icon: 'fa fa-leaf',
    },
    error: {
      title: 'CSR Data Loading Failed',
      description: "We couldn't load CSR and sustainability information.",
      icon: 'fa fa-exclamation-triangle',
    },
    noData: {
      title: 'No CSR Information',
      description: "We haven't found information about this company's CSR initiatives.",
      icon: 'fa fa-leaf',
    },
  },
  digital: {
    loading: {
      title: 'Analyzing Digital Presence',
      description: 'Scanning digital footprint and online presence...',
      icon: 'fa fa-globe',
    },
    error: {
      title: 'Digital Analysis Failed',
      description: "We couldn't analyze the company's digital presence.",
      icon: 'fa fa-exclamation-triangle',
    },
    noData: {
      title: 'Limited Digital Presence',
      description: "We found limited information about this company's digital presence.",
      icon: 'fa fa-globe',
    },
  },
}

// Computed properties for current content
const currentConfig = computed(
  () => pageConfigs[primaryPageType.value as keyof typeof pageConfigs] || pageConfigs.profile,
)

const loadingTitle = computed(() => props.loadingTitle || currentConfig.value.loading.title)
const loadingDescription = computed(
  () => props.loadingDescription || currentConfig.value.loading.description,
)
const loadingIcon = computed(
  () => props.loadingIcon || `${currentConfig.value.loading.icon} fa-spin`,
)

const errorTitle = computed(() => props.errorTitle || currentConfig.value.error.title)
const errorDescription = computed(
  () => props.errorDescription || currentConfig.value.error.description,
)
const errorIcon = computed(() => props.errorIcon || currentConfig.value.error.icon)

const noDataTitle = computed(() => props.noDataTitle || currentConfig.value.noData.title)
const noDataDescription = computed(
  () => props.noDataDescription || currentConfig.value.noData.description,
)
const noDataIcon = computed(() => props.noDataIcon || currentConfig.value.noData.icon)
</script>
