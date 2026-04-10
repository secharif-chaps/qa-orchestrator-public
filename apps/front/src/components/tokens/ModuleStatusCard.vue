<template>
  <div class="border-primary-lighter-stroke rounded-sm border bg-white p-4">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <!-- Module Icon -->
        <Badge
          :intent="isEnabled ? 'success' : 'danger'"
          :label="isEnabled ? $t('settings.tokens.enabled') : $t('settings.tokens.disabled')"
          :icon="moduleIcon"
          variant="secondary"
        >
        </Badge>

        <!-- Module Info -->
        <div>
          <h3 class="font-medium capitalize">
            {{ $t(`settings.tokens.modules.${module}.name`, module) }}
          </h3>
          <p class="text-neutral-black-font text-sm">
            {{ moduleDescription }}
          </p>
        </div>
      </div>

      <!-- Enable/Disable Toggle -->
      <Switch
        :id="`module-toggle-${module}`"
        :model-value="isEnabled"
        :disabled="isToggling"
        @update:model-value="handleToggle"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { useToggleModule } from '@/mutations/tokens'
import type { ModuleName } from '@/types/tokens'
import { Badge, Switch } from '@owlint/feathers-vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

interface Props {
  module: ModuleName
  isEnabled: boolean
  organizationId: string
}

const props = defineProps<Props>()

const emit = defineEmits<{
  refresh: []
}>()

// Mutation for toggling module
const { toggleModule, isLoading: isToggling } = useToggleModule()

// Module icon mapping (core modules only)
const moduleIcons: Record<ModuleName, string> = {
  screen: 'fa fa-search',
  target: 'fa fa-bullseye',
  explore: 'fa fa-compass',
}

// Computed properties
const moduleIcon = computed(() => moduleIcons[props.module] || 'fa fa-cog')

const moduleDescription = computed(() =>
  t(`settings.tokens.modules.${props.module}.description`, getDefaultDescription(props.module)),
)

// Default descriptions for modules (core modules only)
function getDefaultDescription(module: ModuleName): string {
  const descriptions: Record<ModuleName, string> = {
    screen: 'Search and create company profiles',
    target: 'Target specific companies',
    explore: 'Explore company relationships',
  }
  return descriptions[module] || 'Module functionality'
}

// Toggle module enabled state
async function handleToggle() {
  try {
    await toggleModule({
      organizationId: props.organizationId,
      module: props.module,
      enabled: !props.isEnabled,
    })
    emit('refresh')
  } catch (error) {
    console.error('Failed to toggle module:', error)
  }
}
</script>
