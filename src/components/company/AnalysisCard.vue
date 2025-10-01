<template>
  <div
    class="bg-bg3 min-h-64 rounded-card border border-border-2 p-6 hover:shadow-shadow-2 transition-all duration-300 cursor-pointer group relative overflow-hidden flex flex-col gap-4"
    :class="{
      'opacity-60 cursor-not-allowed': disabled,
      'hover:border-primary/50': !disabled && !isLoading,
    }"
    @click="handleClick"
  >
    <!-- Status Badge (top-right) -->
    <div class="absolute top-4 right-4">
      <Badge v-if="taskStatus" :variant="statusVariant" :label="statusLabel" size="xs" dot />
    </div>

    <!-- Header -->
    <div class="flex items-start gap-4">
      <div
        class="size-8 rounded-card flex items-center justify-center flex-shrink-0 transition-colors"
      >
        <i v-if="isLoading" class="fas fa-spinner fa-spin text-xl" :class="iconColorClass"></i>
        <i v-else :class="[icon, 'text-xl', iconColorClass]"></i>
      </div>

      <div class="flex-1 min-w-0">
        <h3 class="text-lg font-semibold mb-1 group-hover:text-primary transition-colors">
          {{ title }}
        </h3>
      </div>
    </div>

    <!-- AI Insights Preview -->
    <div v-if="hasInsights" class="">
      <p class="text-sm text-secondary line-clamp-3">
        {{ insightsPreview }}
      </p>
      <button
        class="mt-2 text-xs text-primary hover:text-primary/80 transition-colors font-medium flex items-center gap-1"
      >
        <span>Lire la suite</span>
        <i class="fas fa-arrow-right text-[10px]"></i>
      </button>
    </div>

    <!-- Loading State Overlay -->
    <div
      v-if="isLoading"
      class="absolute inset-0 bg-bg1/80 backdrop-blur-sm flex items-center justify-center rounded-card"
    >
      <div class="flex items-center gap-3 text-base text-secondary">
        <i class="fas fa-spinner fa-spin text-xl"></i>
        <span>Analyse en cours...</span>
      </div>
    </div>

    <!-- Error State Overlay -->
    <div
      v-if="hasError"
      class="absolute inset-0 bg-bg1/80 backdrop-blur-sm flex items-center justify-center rounded-card p-6"
    >
      <Alert
        variant="error"
        title="Erreur"
        :message="errorMessage || 'Une erreur est survenue lors de l\'analyse'"
        icon="fa fa-exclamation-triangle"
        :dismissible="false"
      />
    </div>

    <!-- No Data State -->
    <div v-else-if="!hasInsights && !isLoading" class="mt-4 pt-4 border-t border-border-2">
      <p class="text-sm text-secondary italic">Aucune donnée disponible pour cette section</p>
    </div>

    <!-- Disabled Overlay -->
    <div
      v-if="disabled"
      class="absolute inset-0 bg-bg1/80 backdrop-blur-sm flex items-center justify-center rounded-card"
    >
      <Badge variant="accent" label="Bientôt disponible" size="sm" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Badge from '@/components/ui/Badge.vue'
import Alert from '@/components/ui/Alert.vue'
import type { TaskStatus } from '@/types/task'

interface Props {
  title: string
  description: string
  icon: string
  insights?: string | null
  taskStatus?: TaskStatus | null
  errorMessage?: string | null
  disabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  disabled: false,
  insights: null,
  taskStatus: null,
  errorMessage: null,
})

const emit = defineEmits<{
  click: []
}>()

// Computed properties
const isLoading = computed(() => props.taskStatus === 'running' || props.taskStatus === 'pending')
const hasError = computed(() => props.taskStatus === 'error')
const hasInsights = computed(() => !!props.insights && props.insights.trim().length > 0)

const insightsPreview = computed(() => {
  if (!props.insights) return ''
  // Truncate to ~150 characters for preview
  const maxLength = 150
  if (props.insights.length <= maxLength) return props.insights
  return props.insights.substring(0, maxLength).trim() + '...'
})

const statusVariant = computed(() => {
  switch (props.taskStatus) {
    case 'succeeded':
      return 'success'
    case 'error':
      return 'error'
    case 'running':
      return 'warning'
    case 'pending':
      return 'info'
    default:
      return 'accent'
  }
})

const statusLabel = computed(() => {
  switch (props.taskStatus) {
    case 'succeeded':
      return 'Terminé'
    case 'error':
      return 'Erreur'
    case 'running':
      return 'En cours'
    case 'pending':
      return 'En attente'
    default:
      return 'Non démarré'
  }
})

const iconContainerClass = computed(() => {
  if (props.disabled) {
    return 'bg-bg2 group-hover:bg-bg2'
  }
  if (isLoading.value) {
    return 'bg-warning-500/10 group-hover:bg-warning-500/20'
  }
  if (hasError.value) {
    return 'bg-error-500/10 group-hover:bg-error-500/20'
  }
  return 'bg-primary/10 group-hover:bg-primary/20'
})

const iconColorClass = computed(() => {
  if (props.disabled) {
    return 'text-secondary'
  }
  if (isLoading.value) {
    return 'text-warning-500'
  }
  if (hasError.value) {
    return 'text-error-500'
  }
  return 'text-primary'
})

const handleClick = () => {
  if (!props.disabled) {
    emit('click')
  }
}
</script>

<style scoped>
.line-clamp-3 {
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
