<template>
  <div class="bg-base-100 border-primary-stroke rounded-lg border p-4">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div
          class="flex h-10 w-10 items-center justify-center rounded-full"
          :class="isEnabled ? 'bg-primary/10 text-primary' : 'bg-base-300 text-secondary'"
        >
          <i :class="moduleIcon" class="text-lg"></i>
        </div>
        <div>
          <h3 class="font-medium capitalize">
            {{ $t(`settings.tokens.modules.${module}.name`, module) }}
          </h3>
          <p class="text-secondary text-sm">
            {{ moduleDescription }}
          </p>
        </div>
      </div>

      <!-- Enable/Disable Toggle -->
      <div class="flex items-center gap-3">
        <Tag
          :variant="isEnabled ? 'success' : 'slate'"
          :label="isEnabled ? $t('settings.tokens.enabled') : $t('settings.tokens.disabled')"
          size="sm"
          :dot="true"
        />

        <label class="relative inline-flex cursor-pointer items-center">
          <input
            type="checkbox"
            :checked="isEnabled"
            :disabled="isToggling"
            class="peer sr-only"
            @change="handleToggle"
          />
          <div
            class="bg-base-300 peer-focus:ring-primary/20 peer after:border-primary-stroke peer-checked:bg-primary relative h-6 w-11 rounded-full peer-focus:ring-4 peer-focus:outline-none peer-disabled:cursor-not-allowed peer-disabled:opacity-50 after:absolute after:top-[2px] after:left-[2px] after:h-5 after:w-5 after:rounded-full after:border after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white"
          ></div>
        </label>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Tag from '@/components/ui/Tag.vue'
import { useToggleModule } from '@/mutations/tokens'
import type { ModuleName } from '@/types/tokens'

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

// Module icon mapping
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

// Default descriptions for modules
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
