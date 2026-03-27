<template>
  <div class="bg-base-100 border-primary-stroke rounded-lg border p-4">
    <div class="flex flex-col gap-4">
      <!-- Main row: Badge, Info, Toggle -->
      <div class="flex items-center justify-between">
        <div class="flex min-w-0 items-center gap-3">
          <!-- Feature Flag Icon -->
          <Badge
            :intent="isEnabled ? 'success' : 'danger'"
            :label="
              isEnabled
                ? $t('settings.featureFlags.enabled', 'Enabled')
                : $t('settings.featureFlags.disabled', 'Disabled')
            "
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
            <p class="text-secondary text-sm">
              {{ $t(flagConfig.descriptionKey, defaultDescription) }}
            </p>
          </div>
        </div>

        <!-- Enable/Disable Toggle -->
        <Switch
          :id="`feature-flag-toggle-${flag}`"
          :model-value="isEnabled"
          @update:model-value="handleToggle"
        />
      </div>

      <!-- URL Input for Discover flag (shown when enabled) -->
      <div v-if="isDiscoverFlag && isEnabled" class="flex flex-col gap-2">
        <Input
          id="discover-url-input"
          v-model="urlInput"
          type="url"
          :label="$t('settings.featureFlags.discover.urlLabel', 'External URL')"
          :placeholder="
            $t('settings.featureFlags.discover.urlPlaceholder', 'https://discover.example.com')
          "
          :error="urlError"
          :disabled="isSavingUrl"
          icon="fa fa-external-link"
          @blur="handleUrlBlur"
        />
        <p class="text-secondary text-xs">
          {{
            $t(
              'settings.featureFlags.discover.urlHint',
              'Enter the HTTPS URL for the Discover dashboard.',
            )
          }}
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
const { toggleFeatureFlag } = useToggleFeatureFlag()

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
  pappers: 'Fetch company data from Pappers API',
  worldcheck: 'Due diligence screening via WorldCheck One API',
}

const defaultDescription = computed(
  () => defaultDescriptions[props.flag] || 'Feature functionality',
)

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
    urlError.value = t('settings.featureFlags.discover.urlError', 'Please enter a valid HTTPS URL')
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
    urlError.value = t('settings.featureFlags.discover.saveError', 'Failed to save URL')
  } finally {
    isSavingUrl.value = false
  }
}

// Toggle feature flag enabled state
const handleToggle = async () => {
  try {
    const newEnabled = !props.isEnabled

    // Toggle only changes enabled state, never touches config
    // (config with API keys is managed via data-sources endpoint)
    await toggleFeatureFlag({
      organizationId: props.organizationId,
      flag: props.flag,
      enabled: newEnabled,
    })
    emit('refresh')
  } catch (error) {
    console.error('Failed to toggle feature flag:', error)
  }
}
</script>
