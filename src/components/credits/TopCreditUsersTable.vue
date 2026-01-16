<template>
  <div class="bg-base-100 rounded-lg border border-primary-stroke overflow-hidden">
    <table class="w-full">
      <thead class="bg-base-200">
        <tr>
          <th class="px-4 py-3 text-center text-xs font-medium text-secondary uppercase tracking-wider w-16">
            {{ $t('credits.topUsers.rank', 'Rang') }}
          </th>
          <th class="px-4 py-3 text-left text-xs font-medium text-secondary uppercase tracking-wider">
            {{ $t('credits.topUsers.user', 'Utilisateur') }}
          </th>
          <th class="px-4 py-3 text-right text-xs font-medium text-secondary uppercase tracking-wider">
            {{ $t('credits.topUsers.credits', 'Crédits') }}
          </th>
        </tr>
      </thead>
      <tbody>
        <TopCreditUserRow
          v-for="user in users"
          :key="user.userId"
          :user="user"
        />
      </tbody>
    </table>

    <!-- Empty state -->
    <div v-if="users.length === 0" class="p-8 text-center">
      <i class="fa fa-users text-2xl text-secondary mb-2"></i>
      <p class="text-sm text-secondary">
        {{ $t('credits.topUsers.noData', 'Aucun utilisateur trouvé') }}
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * Table displaying top credit-consuming users.
 */
import type { TopCreditUser } from '@/types/credits'
import TopCreditUserRow from './TopCreditUserRow.vue'

interface Props {
  users: TopCreditUser[]
}

defineProps<Props>()
</script>
