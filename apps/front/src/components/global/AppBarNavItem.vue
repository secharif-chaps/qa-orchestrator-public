<template>
  <button
    class="text-sage-900 dark:text-sage-300 focus-visible:ring-accent-700 relative flex size-9 cursor-pointer items-center justify-center rounded p-2 transition-colors focus-visible:ring-2 focus-visible:outline-none"
    :class="[activeClass]"
  >
    <Icon :icon class="text-xl" :class="{ 'mb-1': active }" />
    <!-- Active indicator bar -->
    <span
      v-if="active"
      class="bg-sage-400 absolute bottom-px left-1/2 h-1 w-4 -translate-x-1/2 rounded-full"
    />
  </button>
</template>

<script lang="ts" setup>
import { Icon } from '@owlint/feathers-vue'
import { computed } from 'vue'

interface Props {
  icon: string
  active: boolean
}

const { icon, active } = defineProps<Props>()

const pressedClass = 'active:bg-sage-300 active:text-almond-950'
const hoverClass = 'hover:bg-sage-950 hover:text-white'

const activeClass = computed(() => {
  if (active) {
    return ''
  }
  return [pressedClass, hoverClass]
})
</script>
