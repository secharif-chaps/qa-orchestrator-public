<template>
  <tr class="border-b border-primary-stroke last:border-b-0 hover:bg-base-200/50 transition-colors">
    <!-- Rank -->
    <td class="px-4 py-3 text-center">
      <span
        class="inline-flex items-center justify-center w-7 h-7 rounded-full text-sm font-medium"
        :class="rankClasses"
      >
        {{ user.rank }}
      </span>
    </td>

    <!-- User -->
    <td class="px-4 py-3">
      <div class="flex items-center gap-3">
        <!-- Avatar -->
        <div
          class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-sm font-medium text-primary"
        >
          {{ user.initials }}
        </div>

        <!-- Name and email -->
        <div>
          <div class="font-medium">{{ user.fullName }}</div>
          <div class="text-sm text-secondary">{{ user.email }}</div>
        </div>
      </div>
    </td>

    <!-- Credits consumed as badge -->
    <td class="px-4 py-3 text-right">
      <span class="inline-flex items-center px-3 py-1 rounded-full bg-primary-light text-primary text-sm font-medium">
        {{ formattedCredits }} {{ $t('credits.usedCredits') }}
      </span>
    </td>
  </tr>
</template>

<script setup lang="ts">
/**
 * Single user row in the top credit users table.
 * Displays rank, avatar, name, email, and credits consumed.
 */
import { computed } from 'vue'
import type { TopCreditUser } from '@/types/credits'

interface Props {
  user: TopCreditUser
}

const props = defineProps<Props>()

const formattedCredits = computed(() => {
  return props.user.creditsConsumed.toLocaleString()
})

// Special styling for top 3 ranks
const rankClasses = computed(() => {
  switch (props.user.rank) {
    case 1:
      return 'bg-warning/20 text-warning'
    case 2:
      return 'bg-secondary/20 text-secondary'
    case 3:
      return 'bg-accent/20 text-accent'
    default:
      return 'bg-base-200 text-secondary'
  }
})
</script>
