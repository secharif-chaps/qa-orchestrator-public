<template>
  <div class="bg-base-100 rounded-lg p-4 border border-primary-stroke">
    <div class="flex flex-col gap-4">
      <!-- Main row: Badge, Info, Toggle -->
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3 min-w-0">
          <!-- Feature Flag Icon -->
          <Badge
            :intent="isEnabled ? 'success' : 'danger'"
            :label="isEnabled ? $t('featureFlags.enabled', 'Enabled') : $t('featureFlags.disabled', 'Disabled')"
            :icon="flagIcon"
            variant="secondary"
            class="shrink-0"
          >
          </Badge>

          <!-- Feature Flag Info -->
          <div>
            <h3 class="font-medium capitalize">
              {{ $t(flagConfig.labelKey, flagName) }}
            </h3>
            <p class="text-sm text-secondary">
              {{ $t(flagConfig.descriptionKey, defaultDescription) }}
            </p>
          </div>
        </div>

        <!-- Enable/Disable Toggle -->
        <Switch
          :id="`feature-flag-toggle-${flag}`"
          :model-value="isEnabled"
          :disabled="isToggling"
          @update:model-value="handleToggle"
        />
      </div>

      <!-- URL Input for Discover flag (shown when enabled) -->
      <div v-if="isDiscoverFlag && isEnabled" class="flex flex-col gap-2">
        <Input
          v-model="urlInput"
          type="url"
          :label="$t('featureFlags.discover.urlLabel', 'External URL')"
          :placeholder="$t('featureFlags.discover.urlPlaceholder', 'https://discover.example.com')"
          :error="urlError"
          :disabled="isSavingUrl"
          icon="fa fa-external-link"
          @blur="handleUrlBlur"
        />
        <p class="text-xs text-secondary">
          {{ $t('featureFlags.discover.urlHint', 'Enter the HTTPS URL for the Discover dashboard.') }}
        </p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToggleFeatureFlag } from '@/mutations/feature-flags'
import { FEATURE_FLAG_CONFIG, type FeatureFlagName } from '@/types/feature-flags'
import { Badge, Switch, Input } from '@owlint/feathers-vue'

interface Props {
  flag: FeatureFlagName
  isEnabled: boolean
  organizationId: string
  config?: Record<string, unknown> | null
}

const props = withDefaults(defineProps<Props>(), {
  config: null,
})

const emit = defineEmits<{
  refresh: []
}>()

const { t } = useI18n()

// Mutation for toggling feature flag
const { toggleFeatureFlag, isPending: isToggling } = useToggleFeatureFlag()

// Get flag config
const flagConfig = computed(() => FEATURE_FLAG_CONFIG[props.flag])
const flagIcon = computed(() => flagConfig.value?.icon || 'fa fa-flag')
const flagName = computed(() => props.flag.charAt(0).toUpperCase() + props.flag.slice(1))

// Check if this is the discover flag
const isDiscoverFlag = computed(() => props.flag === 'discover')

// Default descriptions for feature flags
const defaultDescriptions: Record<FeatureFlagName, string> = {
  translation: 'Translate company data to other languages',
  discover: 'Access external Discover dashboard',
}

const defaultDescription = computed(() => defaultDescriptions[props.flag] || 'Feature functionality')

// URL input state for discover flag
const urlInput = ref<string>((props.config?.url as string) || '')
const urlError = ref<string>('')
const isSavingUrl = ref(false)

// Track original URL value to detect changes
const originalUrl = ref<string>((props.config?.url as string) || '')

// Watch for config prop changes (e.g., when data is refetched)
watch(
  () => props.config,
  (newConfig) => {
    const newUrl = (newConfig?.url as string) || ''
    urlInput.value = newUrl
    originalUrl.value = newUrl
    urlError.value = ''
  },
  { immediate: true },
)

/**
 * Validates HTTPS URL format.
 */
const validateHttpsUrl = (url: string): boolean => {
  if (!url || url.trim() === '') {
    return true // Empty URL is valid (optional field)
  }

  try {
    const parsedUrl = new URL(url)
    return parsedUrl.protocol === 'https:'
  } catch {
    return false
  }
}

/**
 * Handle URL input blur - validate and save if changed.
 */
const handleUrlBlur = async () => {
  const trimmedUrl = urlInput.value.trim()

  // Validate URL format
  if (trimmedUrl && !validateHttpsUrl(trimmedUrl)) {
    urlError.value = t('featureFlags.discover.urlError', 'Please enter a valid HTTPS URL')
    return
  }

  // Clear any previous error
  urlError.value = ''

  // Only save if URL has changed
  if (trimmedUrl === originalUrl.value) {
    return
  }

  // Save the URL via mutation
  try {
    isSavingUrl.value = true
    await toggleFeatureFlag({
      organizationId: props.organizationId,
      flag: props.flag,
      enabled: props.isEnabled,
      config: trimmedUrl ? { url: trimmedUrl } : null,
    })
    originalUrl.value = trimmedUrl
    emit('refresh')
  } catch (error) {
    console.error('Failed to save URL config:', error)
    urlError.value = t('featureFlags.discover.saveError', 'Failed to save URL')
  } finally {
    isSavingUrl.value = false
  }
}

// Toggle feature flag enabled state
const handleToggle = async () => {
  try {
    const newEnabled = !props.isEnabled

    // When disabling, preserve the existing URL config
    // When enabling, also preserve any existing config
    await toggleFeatureFlag({
      organizationId: props.organizationId,
      flag: props.flag,
      enabled: newEnabled,
      config: props.config,
    })
    emit('refresh')
  } catch (error) {
    console.error('Failed to toggle feature flag:', error)
  }
}
</script>
