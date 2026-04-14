<template>
  <div
    class="flex shrink-0 items-center justify-center rounded-full font-semibold"
    :class="[sizeClasses, variantClasses]"
  >
    {{ displayInitials }}
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const getInitials = (name: string): string => {
  const parts = name.trim().split(/\s+/)
  if (parts.length === 0 || parts[0] === '') return '?'
  return parts
    .slice(0, 2)
    .map((w) => w[0]?.toUpperCase() ?? '')
    .join('')
}

interface Props {
  /** Full name to extract initials from */
  name?: string
  /** Pre-computed initials (skips extraction) */
  initials?: string
  size?: 'xs' | 'sm' | 'md' | 'lg'
  variant?: 'neutral' | 'primary' | 'secondary' | 'primary-light'
}

const { name, initials, size = 'md', variant = 'neutral' } = defineProps<Props>()

const displayInitials = computed(() => initials || (name ? getInitials(name) : '?'))

const sizeClasses = computed(() => {
  const map = {
    xs: 'size-6 text-xs',
    sm: 'size-8 text-sm',
    md: 'size-9 text-xs',
    lg: 'size-10 text-sm',
  }
  return map[size]
})

const variantClasses = computed(() => {
  const map = {
    neutral: 'bg-neutral-disabled text-neutral-font',
    primary: 'bg-primary text-white',
    secondary: 'bg-secondary text-white',
    'primary-light': 'bg-primary/10 text-primary',
  }
  return map[variant]
})
</script>
