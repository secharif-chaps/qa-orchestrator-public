<template>
  <tr class="border-b border-primary-stroke last:border-b-0 hover:bg-base-200/50 transition-colors">
    <!-- Rank -->
    <td class="px-4 py-3 text-center">
      <!-- Medal style for top 3 -->
      <div v-if="isTopThree" class="relative inline-flex">
        <span
          class="inline-flex items-center justify-center w-9 h-9 rounded-full text-sm font-bold shadow-md"
          :class="medalClasses"
        >
          {{ user.rank }}
        </span>
        <!-- Trophy icon floating bottom-right -->
        <span
          class="absolute -bottom-2 -right-2 w-5 h-5 rounded-full flex items-center justify-center text-[10px] shadow-sm"
          :class="trophyClasses"
        >
          <i class="fa fa-trophy"></i>
        </span>
      </div>
      <!-- Regular rank for others -->
      <span
        v-else
        class="inline-flex items-center justify-center w-7 h-7 rounded-full text-sm font-medium bg-base-200 text-secondary"
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

// Check if user is in top 3
const isTopThree = computed(() => props.user.rank <= 3)

// Medal background and text colors for top 3
const medalClasses = computed(() => {
  switch (props.user.rank) {
    case 1:
      // Gold medal
      return 'bg-gradient-to-br from-amber-300 to-amber-500 text-amber-900'
    case 2:
      // Silver medal
      return 'bg-gradient-to-br from-slate-300 to-slate-400 text-slate-700'
    case 3:
      // Bronze medal
      return 'bg-gradient-to-br from-orange-300 to-orange-500 text-orange-900'
    default:
      return ''
  }
})

// Trophy icon background colors
const trophyClasses = computed(() => {
  switch (props.user.rank) {
    case 1:
      return 'bg-amber-600 text-amber-100'
    case 2:
      return 'bg-slate-500 text-slate-100'
    case 3:
      return 'bg-orange-600 text-orange-100'
    default:
      return ''
  }
})
</script>
