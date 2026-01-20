<template>
  <Table :fields="fields" :items="users" :row-key="(item) => item.userId">
    <!-- Rank column with medal styling -->
    <template #cell(rank)="{ item }">
      <td class="px-4 py-3 text-center">
        <!-- Medal style for top 3 -->
        <div v-if="item.rank <= 3" class="relative inline-flex">
          <span
            class="inline-flex items-center justify-center w-9 h-9 rounded-full text-sm font-bold shadow-md"
            :class="getMedalClasses(item.rank)"
          >
            {{ item.rank }}
          </span>
          <!-- Trophy icon floating bottom-right -->
          <span
            class="absolute -bottom-1 -right-1 w-5 h-5 rounded-full flex items-center justify-center text-[10px] shadow-sm"
            :class="getTrophyClasses(item.rank)"
          >
            <i class="fa fa-trophy"></i>
          </span>
        </div>
        <!-- Regular rank for others -->
        <span
          v-else
          class="inline-flex items-center justify-center w-7 h-7 rounded-full text-sm font-medium bg-base-200 text-secondary"
        >
          {{ item.rank }}
        </span>
      </td>
    </template>

    <!-- User column with avatar -->
    <template #cell(user)="{ item }">
      <td class="px-4 py-3">
        <div class="flex items-center gap-3">
          <!-- Avatar -->
          <div
            class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-sm font-medium text-primary"
          >
            {{ item.initials }}
          </div>

          <!-- Name and email -->
          <div>
            <div class="font-medium">{{ item.fullName }}</div>
            <div class="text-sm text-secondary">{{ item.email }}</div>
          </div>
        </div>
      </td>
    </template>

    <!-- Credits column as badge -->
    <template #cell(credits)="{ item }">
      <td class="px-4 py-3 text-right">
        <span class="inline-flex items-center px-3 py-1 rounded-full bg-primary-light text-primary text-sm font-medium">
          {{ item.creditsConsumed.toLocaleString() }} {{ $t('credits.usedCredits') }}
        </span>
      </td>
    </template>

    <!-- Empty state -->
    <template #empty>
      <div class="p-8 text-center">
        <i class="fa fa-users text-2xl text-secondary mb-2"></i>
        <p class="text-sm text-secondary">
          {{ $t('credits.topUsers.noData', 'Aucun utilisateur trouvé') }}
        </p>
      </div>
    </template>
  </Table>
</template>

<script setup lang="ts">
/**
 * Table displaying top credit-consuming users using Vuellar Table component.
 */
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Table } from '@owlint/feathers-vue'
import type { TopCreditUser } from '@/types/credits'

interface Props {
  users: TopCreditUser[]
}

defineProps<Props>()

const { t } = useI18n()

const fields = computed(() => [
  { key: 'rank', label: t('credits.topUsers.rank', 'Rang'), class: 'w-16 text-center' },
  { key: 'user', label: t('credits.topUsers.user', 'Utilisateur') },
  { key: 'credits', label: t('credits.topUsers.credits', 'Crédits'), class: 'text-right' },
])

// Medal background and text colors for top 3
const getMedalClasses = (rank: number): string => {
  switch (rank) {
    case 1:
      return 'bg-gradient-to-br from-amber-300 to-amber-500 text-amber-900'
    case 2:
      return 'bg-gradient-to-br from-slate-300 to-slate-400 text-slate-700'
    case 3:
      return 'bg-gradient-to-br from-orange-300 to-orange-500 text-orange-900'
    default:
      return ''
  }
}

// Trophy icon background colors
const getTrophyClasses = (rank: number): string => {
  switch (rank) {
    case 1:
      return 'bg-amber-600 text-amber-100'
    case 2:
      return 'bg-slate-500 text-slate-100'
    case 3:
      return 'bg-orange-600 text-orange-100'
    default:
      return ''
  }
}
</script>
