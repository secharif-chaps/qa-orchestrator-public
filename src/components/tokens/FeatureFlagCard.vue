<template>
  <div class="bg-base-100 rounded-lg p-4 border border-primary-stroke">
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
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useToggleFeatureFlag } from '@/mutations/feature-flags'
import { FEATURE_FLAG_CONFIG, type FeatureFlagName } from '@/types/feature-flags'
import { Badge, Switch } from '@owlint/feathers-vue'

interface Props {
  flag: FeatureFlagName
  isEnabled: boolean
  organizationId: string
}

const props = defineProps<Props>()

const emit = defineEmits<{
  refresh: []
}>()

// Mutation for toggling feature flag
const { toggleFeatureFlag, isPending: isToggling } = useToggleFeatureFlag()

// Get flag config
const flagConfig = computed(() => FEATURE_FLAG_CONFIG[props.flag])
const flagIcon = computed(() => flagConfig.value?.icon || 'fa fa-flag')
const flagName = computed(() => props.flag.charAt(0).toUpperCase() + props.flag.slice(1))

// Default descriptions for feature flags
const defaultDescriptions: Record<FeatureFlagName, string> = {
  translation: 'Translate company data to other languages',
}

const defaultDescription = computed(() => defaultDescriptions[props.flag] || 'Feature functionality')

// Toggle feature flag enabled state
async function handleToggle() {
  try {
    await toggleFeatureFlag({
      organizationId: props.organizationId,
      flag: props.flag,
      enabled: !props.isEnabled,
    })
    emit('refresh')
  } catch (error) {
    console.error('Failed to toggle feature flag:', error)
  }
}
</script>
