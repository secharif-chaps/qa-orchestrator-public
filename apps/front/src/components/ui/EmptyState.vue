<template>
  <div
    class="flex items-center justify-center"
    :class="{
      'flex-1': verticalAlign === 'center',
    }"
  >
    <div class="flex flex-col items-center justify-center px-4 py-12 text-center">
      <Icon
        :icon="icon"
        class="text-neutral-light-font mb-4 text-5xl"
        :lib="fill ? 'fa-solid' : 'fa-regular'"
      />
      <!-- Hide the heading when a custom slot is provided without a title, to avoid a double-heading with the slot's own content -->
      <h3 v-if="title || !$slots.default" class="text-neutral-font mb-1 text-lg font-medium">
        {{ title || $t('common.empty') }}
      </h3>
      <div v-if="$slots.default || description" class="text-neutral-muted-font max-w-112">
        <slot>
          <p>{{ description }}</p>
        </slot>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Icon } from '@owlint/feathers-vue'

interface Props {
  title?: string
  description?: string
  icon?: string
  fill?: boolean
  verticalAlign?: 'top' | 'center'
}

const {
  icon = 'fa-circle-info',
  verticalAlign = 'top',
  description = '',
  title = '',
} = defineProps<Props>()
</script>
