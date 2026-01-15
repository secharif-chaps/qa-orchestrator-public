<template>
  <tr class="hover:bg-base-200 transition-colors">
    <!-- Organization Name -->
    <td class="px-6 py-4 whitespace-nowrap">
      <span class="text-sm font-medium">{{ organization.organization_name }}</span>
    </td>

    <!-- Companies Count -->
    <td class="px-6 py-4 whitespace-nowrap text-center">
      <span class="text-sm font-medium">{{ formattedCount }}</span>
    </td>

    <!-- Percentage of Total -->
    <td class="px-6 py-4 whitespace-nowrap text-right">
      <span class="text-sm text-secondary">{{ formattedPercentage }}</span>
    </td>
  </tr>
</template>

<script setup lang="ts">
/**
 * Reusable table row component for organization usage breakdown.
 *
 * Displays a single organization's company count and percentage
 * of total companies in a consistent, formatted manner.
 */
import { computed } from 'vue'
import type { OrganizationBreakdown } from '@/types/usage'

interface Props {
  /** Organization breakdown data to display */
  organization: OrganizationBreakdown
}

const props = defineProps<Props>()

/**
 * Format the company count with locale-aware number formatting.
 * Example: 1234 -> "1,234"
 */
const formattedCount = computed((): string => {
  return props.organization.companies_count.toLocaleString()
})

/**
 * Format the percentage with one decimal place.
 * Example: 12.5 -> "12.5%"
 */
const formattedPercentage = computed((): string => {
  return `${props.organization.percentage.toFixed(1)}%`
})
</script>
