<template>
  <div class="h-[calc(100vh-140px)] flex flex-col">
    <!-- Header with Total Credits -->
    <div class="flex items-center justify-between border-b-2 shadow border-sage-800 px-4 py-2">
      <h2 class="text-headline-2xl">{{ $t('sidebar.tokens.title', 'Credits') }}</h2>
      <Badge
        variant="success"
        :label="$t('sidebar.tokens.credits', '{count} credits', { count: totalTokens })"
        icon="fa fa-coins"
        rounded
        size="md"
      />
    </div>

    <!-- Token History List -->
    <div class="flex-1 overflow-y-auto px-4 py-4">
      <!-- Loading State -->
      <div v-if="isLoading" class="flex items-center justify-center py-8">
        <i class="fa fa-spinner fa-spin text-sage-400"></i>
      </div>

      <!-- Token History -->
      <div v-else class="space-y-6">
        <!-- Today Section -->
        <div v-if="mockHistory.today.length > 0">
          <h3 class="text-xs font-semibold text-sage-400 uppercase tracking-wider mb-3">{{ $t('sidebar.tokens.yesterday', 'Yesterday') }}</h3>
          <div class="space-y-2">
            <div
              v-for="item in mockHistory.today"
              :key="item.id"
              class="bg-sage-800/50 rounded-lg p-3 hover:bg-sage-800 transition-colors"
            >
              <div class="flex items-start gap-3">
                <!-- Icon -->
                <div
                  class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                  :class="item.icon.bg"
                >
                  <i :class="[item.icon.icon, item.icon.color, 'text-sm']"></i>
                </div>

                <!-- Content -->
                <div class="flex-1 min-w-0">
                  <div class="flex items-start justify-between gap-2">
                    <h4 class="text-sm font-medium text-white truncate">{{ item.title }}</h4>
                    <span
                      class="text-xs font-semibold px-2 py-0.5 rounded-full flex-shrink-0"
                      :class="item.amount < 0 ? 'bg-red-500/20 text-red-300' : 'bg-green-500/20 text-green-300'"
                    >
                      {{ item.amount > 0 ? '+' : '' }}{{ item.amount }}
                    </span>
                  </div>
                  <p class="text-xs text-sage-400 mt-1">{{ item.description }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Date Section (26 septembre) -->
        <div v-if="mockHistory.date.length > 0">
          <h3 class="text-xs font-semibold text-sage-400 uppercase tracking-wider mb-3">26 septembre</h3>
          <div class="space-y-2">
            <div
              v-for="item in mockHistory.date"
              :key="item.id"
              class="bg-sage-800/50 rounded-lg p-3 hover:bg-sage-800 transition-colors"
            >
              <div class="flex items-start gap-3">
                <!-- Icon -->
                <div
                  class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                  :class="item.icon.bg"
                >
                  <i :class="[item.icon.icon, item.icon.color, 'text-sm']"></i>
                </div>

                <!-- Content -->
                <div class="flex-1 min-w-0">
                  <div class="flex items-start justify-between gap-2">
                    <h4 class="text-sm font-medium text-white truncate">{{ item.title }}</h4>
                    <span
                      class="text-xs font-semibold px-2 py-0.5 rounded-full flex-shrink-0"
                      :class="item.amount < 0 ? 'bg-red-500/20 text-red-300' : 'bg-green-500/20 text-green-300'"
                    >
                      {{ item.amount > 0 ? '+' : '' }}{{ item.amount }}
                    </span>
                  </div>
                  <p class="text-xs text-sage-400 mt-1">{{ item.description }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- See All History Link -->
        <div class="pt-2">
          <button
            class="text-sm text-sage-300 hover:text-white transition-colors flex items-center gap-2"
            @click="$router.push('/tokens/history')"
          >
            {{ $t('sidebar.tokens.viewHistory', 'View all history') }}
            <i class="fa fa-arrow-right text-xs"></i>
          </button>
        </div>
      </div>
    </div>

    <!-- Contact Card -->
    <div class="px-4 pb-4">
      <div class="bg-gradient-to-br from-rose-900/20 to-sage-900/20 rounded-lg p-4 border border-rose-500/20">
        <h3 class="text-sm font-semibold text-white mb-2">{{ $t('sidebar.tokens.needMore', 'Need more Credits?') }}</h3>
        <p class="text-xs text-sage-300 mb-3">
          {{ $t('sidebar.tokens.advisor', 'Your ChapsVision advisor') }}<br />
          <span class="font-semibold text-white">Victoire ECHEKÉMAT</span>
        </p>
        <Button
          variant="secondary"
          size="sm"
          :label="$t('sidebar.tokens.contact', 'Contact')"
          icon="fa fa-envelope"
          @click="handleContact"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useQuery } from '@pinia/colada'
import { workspaceModulesQuery } from '@/queries/tokens'
import { useAuthStore } from '@/stores/auth'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'

const authStore = useAuthStore()

// Get workspace ID from current workspace
const workspaceId = computed(() => authStore.currentWorkspace?.id || 1)

// Fetch workspace modules to get total tokens
const { data: modulesData, isLoading } = useQuery(
  workspaceModulesQuery,
  () => ({ workspaceId: workspaceId.value }),
  { enabled: () => !!workspaceId.value }
)

// Calculate total tokens across all modules
const totalTokens = computed(() => {
  if (!modulesData.value?.modules) return 0
  return modulesData.value.modules.reduce((sum, module) => sum + module.token_count, 0)
})

// Mock token history data
const mockHistory = ref({
  today: [
    {
      id: 1,
      title: 'La meilleur recette de pesto',
      description: 'Nouvelle veille créée par Albus Dumbledore',
      amount: -57,
      icon: {
        icon: 'fa fa-file-alt',
        color: 'text-orange-400',
        bg: 'bg-orange-500/20',
      },
    },
    {
      id: 2,
      title: 'Le bon Pesto',
      description: 'Nouvelle Fiche Entreprise créée par Albus Dumbledore',
      amount: -57,
      icon: {
        icon: 'fa fa-building',
        color: 'text-purple-400',
        bg: 'bg-purple-500/20',
      },
    },
  ],
  date: [
    {
      id: 3,
      title: 'La meilleur recette de pesto',
      description: 'Nouvelle veille créée par Albus Dumbledore',
      amount: -57,
      icon: {
        icon: 'fa fa-file-alt',
        color: 'text-orange-400',
        bg: 'bg-orange-500/20',
      },
    },
    {
      id: 4,
      title: 'Le bon Pesto',
      description: 'Nouvelle Fiche Entreprise créée par Albus Dumbledore',
      amount: -57,
      icon: {
        icon: 'fa fa-building',
        color: 'text-purple-400',
        bg: 'bg-purple-500/20',
      },
    },
    {
      id: 5,
      title: 'Le bon Pesto',
      description: 'Nouvelle Fiche Entreprise créée par Albus Dumbledore',
      amount: -57,
      icon: {
        icon: 'fa fa-building',
        color: 'text-purple-400',
        bg: 'bg-purple-500/20',
      },
    },
  ],
})

// Handle contact button
const handleContact = () => {
  // TODO: Implement contact modal or navigation
  console.log('Contact advisor')
}
</script>