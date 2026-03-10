<template>
  <div
    v-if="shouldShow"
    class="bg-base-300 rounded-card border-primary-stroke flex items-center gap-8 border-2 p-6"
  >
    <!-- Chapse Character -->
    <img
      src="@/assets/chapse/head.svg"
      alt="Chapse Assistant"
      class="h-20 w-auto object-contain"
      loading="lazy"
      style="image-rendering: -webkit-optimize-contrast; image-rendering: smooth"
    />

    <!-- Content -->
    <div class="flex flex-col gap-2">
      <h2 class="text-xl font-semibold">{{ title }}</h2>
      <div class="flex gap-2">
        <Button
          variant="secondary"
          size="sm"
          icon="fa fa-magic"
          :label="actionLabel"
          @click="handleSetup"
        />
        <Button
          v-if="showDismiss"
          variant="secondary"
          size="sm"
          :label="dismissLabel"
          @click="handleDismiss"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useChapseAssist } from '@/composables/useChapseAssist'
import { Button } from '@owlint/feathers-vue'

const { t } = useI18n()

interface Props {
  /**
   * Alert title
   */
  title?: string

  /**
   * Alert message
   */
  message?: string

  /**
   * Action button label
   */
  actionLabel?: string

  /**
   * Dismiss button label
   */
  dismissLabel?: string

  /**
   * Show dismiss button
   */
  showDismiss?: boolean

  /**
   * localStorage key for dismiss state
   */
  dismissKey?: string

  /**
   * Force show the alert even if dismissed
   */
  forceShow?: boolean
}

const props = defineProps<Props>()

// Computed props with translations as defaults
const title = computed(
  () => props.title ?? t('chapseAssist.alert.title', 'Get Personalized AI Recommendations'),
)
const actionLabel = computed(
  () => props.actionLabel ?? t('chapseAssist.alert.actionLabel', 'Set Up Now'),
)
const dismissLabel = computed(
  () => props.dismissLabel ?? t('chapseAssist.alert.dismissLabel', 'Maybe Later'),
)
const showDismiss = computed(() => props.showDismiss ?? true)
const dismissKey = computed(() => props.dismissKey ?? 'chapse_assist_alert_dismissed')
const forceShow = computed(() => props.forceShow ?? false)

const emit = defineEmits<{
  setup: []
  dismiss: []
}>()

const router = useRouter()
const { hasAiPreferences, checkHasPreferences } = useChapseAssist()

// Local state
const isDismissed = ref(false)
const isChecking = ref(true)

/**
 * Check if alert should be shown
 */
const shouldShow = computed(() => {
  // Force show if requested
  if (forceShow.value) return true

  // Don't show if dismissed
  if (isDismissed.value) return false

  // Don't show if still checking
  if (isChecking.value) return false

  // Don't show if user already has preferences
  if (hasAiPreferences.value) return false

  return true
})

/**
 * Handle setup button click
 */
function handleSetup() {
  emit('setup')
  router.push('/ai-preferences-setup')
}

/**
 * Handle dismiss button click
 */
function handleDismiss() {
  isDismissed.value = true

  // Save dismiss state to localStorage
  try {
    localStorage.setItem(dismissKey.value, Date.now().toString())
  } catch (error) {
    console.error('Failed to save dismiss state:', error)
  }

  emit('dismiss')
}

/**
 * Check if alert was previously dismissed
 */
function checkDismissState() {
  try {
    const dismissedAt = localStorage.getItem(dismissKey.value)
    if (dismissedAt) {
      // Check if dismissed within last 7 days
      const dismissedTime = parseInt(dismissedAt, 10)
      const now = Date.now()
      const sevenDays = 7 * 24 * 60 * 60 * 1000

      if (now - dismissedTime < sevenDays) {
        isDismissed.value = true
      } else {
        // Clear old dismiss state
        localStorage.removeItem(dismissKey.value)
      }
    }
  } catch (error) {
    console.error('Failed to check dismiss state:', error)
  }
}

/**
 * Initialize component
 */
onMounted(async () => {
  // Check dismiss state
  checkDismissState()

  // Check if user has AI preferences
  try {
    await checkHasPreferences()
  } catch (error) {
    console.error('Failed to check AI preferences:', error)
  } finally {
    isChecking.value = false
  }
})
</script>
