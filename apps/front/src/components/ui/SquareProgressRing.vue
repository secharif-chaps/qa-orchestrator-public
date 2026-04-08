<template>
  <div class="relative inline-flex">
    <svg
      :width="size"
      :height="size"
      :viewBox="`0 0 ${size} ${size}`"
      class="pointer-events-none absolute inset-0 z-10 overflow-visible"
      aria-hidden="true"
    >
      <!-- Track: full-perimeter background ring -->
      <rect
        x="0"
        y="0"
        :width="size"
        :height="size"
        :rx="borderRadius"
        :ry="borderRadius"
        fill="none"
        :stroke-width="strokeWidth"
        :stroke-opacity="showProgress && segments.length > 0 ? 0.25 : 1"
        class="stroke-primary-lighter-stroke transition-[stroke-opacity] duration-300"
      />
      <!-- Colored progress segments -->
      <rect
        v-for="(seg, i) in renderedSegments"
        :key="i"
        x="0"
        y="0"
        :width="size"
        :height="size"
        :rx="borderRadius"
        :ry="borderRadius"
        fill="none"
        :stroke-width="strokeWidth"
        stroke-opacity="1"
        pathLength="100"
        :stroke-dasharray="`${seg.percentage} ${100 - seg.percentage}`"
        :stroke-dashoffset="seg.dashoffset"
        :class="[seg.color, 'transition-[stroke-dasharray] duration-500 ease-out']"
      />
    </svg>
    <slot />
  </div>
</template>

<script setup lang="ts">
import type { ProgressSegment, RenderedSegment } from '@/composables/useTaskProgress'
import { computed, ref, watch } from 'vue'

interface Props {
  segments: ProgressSegment[]
  showProgress?: boolean
  size?: number
  borderRadius?: number
  strokeWidth?: number
}

const {
  segments,
  showProgress = false,
  size = 48,
  borderRadius = 8,
  strokeWidth = 3,
} = defineProps<Props>()

// Compute the offset to shift stroke start from top-left (SVG rect default) to top-center
const topCenterOffset = computed(() => {
  const straightPerSide = size - 2 * borderRadius
  const totalStraight = 4 * straightPerSide
  const totalCurved = 2 * Math.PI * borderRadius
  const perimeter = totalStraight + totalCurved
  const distToCenter = size / 2 - borderRadius
  return (distToCenter / perimeter) * 100
})

const targetSegments = computed<RenderedSegment[]>(() => {
  if (!showProgress || segments.length === 0) return []

  let cumulative = 0
  return segments.map((seg) => {
    const dashoffset = -(topCenterOffset.value + cumulative)
    cumulative += seg.percentage
    return {
      ...seg,
      dashoffset,
    }
  })
})

// Two-phase render: new segments mount at 0% then grow to target on next frame.
// This prevents the browser from transitioning from the SVG default (full stroke = 100%).
const renderedSegments = ref<RenderedSegment[]>([])

watch(
  targetSegments,
  (newSegs, oldSegs) => {
    const hadNone = !oldSegs || oldSegs.length === 0
    const hasNew = newSegs.length > 0

    if (hadNone && hasNew) {
      // First appearance — mount at 0% so the CSS transition grows from zero
      renderedSegments.value = newSegs.map((seg) => ({ ...seg, percentage: 0 }))
      requestAnimationFrame(() => {
        renderedSegments.value = [...targetSegments.value]
      })
    } else {
      renderedSegments.value = [...newSegs]
    }
  },
  { immediate: true },
)
</script>
