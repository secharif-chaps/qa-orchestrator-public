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
        class="mb-4 text-5xl text-gray-300"
        :lib="fill ? 'fa-solid' : 'fa-regular'"
      />
      <h3 v-if="title" class="mb-1 text-lg font-medium text-gray-900">
        {{ title }}
      </h3>
      <h3 v-else class="mb-1 text-lg font-medium text-gray-900">
        {{ $t('common.empty') }}
      </h3>
      <p v-if="description" class="max-w-112 text-gray-500">
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
