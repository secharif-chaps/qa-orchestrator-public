<template>
  <button
    class="flex cursor-pointer items-center gap-1.5 rounded-full border px-3 py-2 text-base leading-[20px] transition-colors duration-200"
    :class="pillClasses"
    @click="$emit('click')"
  >
    <i :class="icon" class="text-[14px]" />
    <span>{{ label }}</span>
    <i class="fa-regular fa-arrow-up-right-from-square text-[14px]" />
  </button>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  label: string
  icon: string
  color: 'indigo' | 'cherry' | 'yellow' | 'cyan'
}

const { color } = defineProps<Props>()

defineEmits<{
  click: []
}>()

// cherry, yellow, cyan palettes are only in target module CSS (not global) — using Tailwind names
// assuming they'll be available globally or migrated later
const PILL_STYLES: Record<string, string> = {
  indigo: 'bg-indigo-100 border-indigo-600 text-indigo-600 hover:bg-indigo-200',
  cherry: 'bg-cherry-100 border-cherry-600 text-cherry-600 hover:bg-cherry-200',
  yellow: 'bg-yellow-100 border-yellow-700 text-yellow-700 hover:bg-yellow-200',
  cyan: 'bg-cyan-100 border-cyan-600 text-cyan-600 hover:bg-cyan-200',
}

const pillClasses = computed(() => PILL_STYLES[color] ?? PILL_STYLES.indigo)
</script>
