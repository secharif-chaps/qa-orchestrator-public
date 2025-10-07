<template>
  <div class="h-[calc(100vh-140px)] flex flex-col">
    <!-- Header -->
    <div class="flex items-center justify-between border-b-2 shadow border-sage-800 px-4 py-2">
      <h2 class="text-headline-2xl">{{ $t('sidebar.notifications.title', 'Notifications') }}</h2>
      <button
        v-if="unreadCount > 0"
        class="text-xs text-sage-300 hover:text-white transition-colors"
        @click="markAllAsRead"
      >
        {{ $t('sidebar.notifications.markAllRead', 'Mark all as read') }}
      </button>
    </div>

    <!-- Notifications List -->
    <div class="flex-1 overflow-y-auto">
      <!-- Loading State -->
      <div v-if="isLoading" class="flex items-center justify-center py-8">
        <i class="fa fa-spinner fa-spin text-sage-400"></i>
      </div>

      <!-- Notifications -->
      <div v-else-if="notifications.length > 0" class="divide-y divide-sage-800">
        <div
          v-for="notification in notifications"
          :key="notification.id"
          class="px-4 py-3 hover:bg-sage-800/30 transition-colors cursor-pointer"
          :class="{ 'bg-sage-800/20': !notification.read }"
          @click="handleNotificationClick(notification)"
        >
          <div class="flex items-start gap-3">
            <!-- Icon -->
            <div
              class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
              :class="notification.icon.bg"
            >
              <i :class="[notification.icon.icon, notification.icon.color]"></i>
            </div>

            <!-- Content -->
            <div class="flex-1 min-w-0">
              <div class="flex items-start justify-between gap-2 mb-1">
                <h4 class="text-sm font-medium text-white">{{ notification.title }}</h4>
                <span
                  v-if="!notification.read"
                  class="w-2 h-2 rounded-full bg-blue-500 flex-shrink-0 mt-1.5"
                ></span>
              </div>
              <p class="text-xs text-sage-400 mb-2">{{ notification.message }}</p>
              <div class="flex items-center gap-3 text-xs text-sage-500">
                <span>{{ notification.time }}</span>
                <span v-if="notification.category" class="flex items-center gap-1">
                  <i class="fa fa-tag"></i>
                  {{ notification.category }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="flex flex-col items-center justify-center py-12 px-4">
        <div class="w-16 h-16 rounded-full bg-sage-800/50 flex items-center justify-center mb-4">
          <i class="fa fa-bell text-2xl text-sage-500"></i>
        </div>
        <h3 class="text-sm font-medium text-white mb-2">{{ $t('sidebar.notifications.noNotifications', 'No notifications') }}</h3>
        <p class="text-xs text-sage-400 text-center">
          {{ $t('sidebar.notifications.upToDate', 'You are up to date! All notifications will appear here.') }}
        </p>
      </div>
    </div>

    <!-- Footer Actions -->
    <div class="border-t border-sage-800 px-4 py-3">
      <button
        class="w-full text-sm text-sage-300 hover:text-white transition-colors flex items-center justify-center gap-2"
        @click="$router.push('/notifications')"
      >
        {{ $t('sidebar.notifications.viewAll', 'View all notifications') }}
        <i class="fa fa-arrow-right text-xs"></i>
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'

interface Notification {
  id: number
  title: string
  message: string
  time: string
  category?: string
  read: boolean
  icon: {
    icon: string
    color: string
    bg: string
  }
  action?: string
}

// Mock loading state
const isLoading = ref(false)

// Mock notifications data
const notifications = ref<Notification[]>([
  {
    id: 1,
    title: 'Nouvelle entreprise ajoutée',
    message: 'Albus Dumbledore a créé la fiche "Pesto Industries"',
    time: 'Il y a 5 minutes',
    category: 'Équipe',
    read: false,
    icon: {
      icon: 'fa fa-building',
      color: 'text-purple-400',
      bg: 'bg-purple-500/20',
    },
    action: '/companies/123',
  },
  {
    id: 2,
    title: 'Dossier partagé',
    message: 'Hermione Granger a partagé le dossier "Clients Q4 2024" avec vous',
    time: 'Il y a 1 heure',
    category: 'Partage',
    read: false,
    icon: {
      icon: 'fa fa-folder',
      color: 'text-blue-400',
      bg: 'bg-blue-500/20',
    },
    action: '/folders/456',
  },
  {
    id: 3,
    title: 'Crédits ajoutés',
    message: 'Votre compte a été crédité de 500 tokens',
    time: 'Il y a 2 heures',
    category: 'Système',
    read: true,
    icon: {
      icon: 'fa fa-coins',
      color: 'text-green-400',
      bg: 'bg-green-500/20',
    },
  },
  {
    id: 4,
    title: 'Veille mise à jour',
    message: 'La veille "Tech Startups" a trouvé 3 nouvelles entreprises',
    time: 'Il y a 3 heures',
    category: 'Veille',
    read: true,
    icon: {
      icon: 'fa fa-rss',
      color: 'text-orange-400',
      bg: 'bg-orange-500/20',
    },
    action: '/veille/789',
  },
  {
    id: 5,
    title: 'Rapport généré',
    message: 'Votre export CSV "Entreprises Tech" est prêt',
    time: 'Hier',
    category: 'Export',
    read: true,
    icon: {
      icon: 'fa fa-file-csv',
      color: 'text-teal-400',
      bg: 'bg-teal-500/20',
    },
  },
  {
    id: 6,
    title: 'Commentaire ajouté',
    message: 'Ron Weasley a commenté la fiche "Magic Corp"',
    time: 'Il y a 2 jours',
    category: 'Équipe',
    read: true,
    icon: {
      icon: 'fa fa-comment',
      color: 'text-rose-400',
      bg: 'bg-rose-500/20',
    },
    action: '/companies/321',
  },
])

// Computed unread count
const unreadCount = computed(() => {
  return notifications.value.filter((n) => !n.read).length
})

// Mark all as read
const markAllAsRead = () => {
  notifications.value.forEach((n) => {
    n.read = true
  })
}

// Handle notification click
const handleNotificationClick = (notification: Notification) => {
  notification.read = true
  if (notification.action) {
    // TODO: Navigate to action
    console.log('Navigate to:', notification.action)
  }
}
</script>