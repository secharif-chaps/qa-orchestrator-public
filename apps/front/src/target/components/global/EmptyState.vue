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
      <h3 v-if="title" class="text-neutral-font mb-1 text-lg font-medium">
        {{ title }}
      </h3>
      <h3 v-else class="text-neutral-font mb-1 text-lg font-medium">
        {{ $t('common.empty') }}
      </h3>
      <p v-if="description" class="text-neutral-muted-font max-w-112">
        {{ description }}
      </p>
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
