<template>
  <div class="dark h-[calc(100vh-140px)] flex flex-col">
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

      <!-- Error State -->
      <div v-else-if="error" class="px-4 py-6">
        <div class="text-center">
          <i class="fa fa-exclamation-triangle text-2xl text-error mb-2"></i>
          <p class="text-sm text-sage-400">
            {{ $t('sidebar.notifications.errorLoading', 'Unable to load notifications') }}
          </p>
        </div>
      </div>

      <!-- Notifications -->
      <div v-else-if="notifications.length > 0" class="divide-y divide-sage-800">
        <NotificationItem
          v-for="notification in notifications"
          :key="notification.id"
          :id="notification.id"
          :icon="notification.icon.icon"
          :icon-color="notification.icon.color"
          :title="notification.title"
          :message="notification.message"
          :time="notification.time"
          :read="notification.read"
          :category="notification.category"
          :action-url="notification.action"
          @click="handleNotificationClick"
          @mark-as-read="markNotificationAsRead"
        />
      </div>

      <!-- Empty State -->
      <div v-else class="flex flex-col items-center justify-center py-12 px-4">
        <div class="w-16 h-16 rounded-full bg-sage-800/50 flex items-center justify-center mb-4">
          <i class="fa fa-bell text-2xl text-sage-500"></i>
        </div>
        <h3 class="text-sm font-medium text-white mb-2">
          {{ $t('sidebar.notifications.noNotifications', 'No notifications') }}
        </h3>
        <p class="text-xs text-sage-400 text-center">
          {{
            $t(
              'sidebar.notifications.upToDate',
              'You are up to date! All notifications will appear here.',
            )
          }}
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
import { useRouter } from 'vue-router'
import { useQuery } from '@pinia/colada'
import NotificationItem from '../ui/NotificationItem.vue'
import { type BadgeColor } from '../ui/Badge.vue'
import { workspaceActivitiesQuery, currentWorkspaceQuery } from '@/queries/workspace'
import { formatRelativeTime } from '@/utils/time'
import { useI18n } from 'vue-i18n'

interface Notification {
  id: string
  title: string
  message: string
  time: string
  category?: string
  read: boolean
  icon: {
    icon: string
    color: BadgeColor
  }
  action?: string
}

const router = useRouter()
const { t } = useI18n()

// Track read notifications (in real app, this would be persisted)
const readNotifications = ref<Set<string>>(new Set())

// Fetch current workspace to get workspace ID
const { data: currentWorkspace } = useQuery(currentWorkspaceQuery, () => ({}))

// Fetch workspace activities
const {
  data: activitiesData,
  isLoading,
  error,
} = useQuery(
  workspaceActivitiesQuery,
  () => ({ workspaceId: currentWorkspace.value?.id ?? 0 }),
  {
    enabled: () => !!currentWorkspace.value?.id,
  },
)

// Transform activities into notifications (limit to 20 most recent)
const notifications = computed<Notification[]>(() => {
  if (!activitiesData.value) return []

  return activitiesData.value.slice(0, 20).map((activity) => {
    const isCompany = activity.type === 'company'
    const notificationId = `${activity.type}-${activity.id}`

    return {
      id: notificationId,
      title: isCompany
        ? t('sidebar.notifications.companyCreated', 'New company added')
        : t('sidebar.notifications.folderCreated', 'New folder created'),
      message: `${activity.owner_username} ${t('sidebar.notifications.activityMessage', 'created')} ${activity.name}`,
      time: formatRelativeTime(activity.created_at),
      category: isCompany
        ? t('sidebar.notifications.categoryCompany', 'Company')
        : t('sidebar.notifications.categoryFolder', 'Folder'),
      read: readNotifications.value.has(notificationId),
      icon: {
        icon: isCompany ? 'fa fa-building' : 'fa fa-folder',
        color: isCompany ? 'accent' : 'success',
      },
      action: isCompany ? `/companies/${activity.id}` : `/folders/${activity.id}`,
    }
  })
})

// Computed unread count
const unreadCount = computed(() => {
  return notifications.value.filter((n) => !n.read).length
})

// Mark all as read
function markAllAsRead() {
  notifications.value.forEach((n) => {
    readNotifications.value.add(n.id)
  })
}

// Mark single notification as read
function markNotificationAsRead(id: number | string) {
  readNotifications.value.add(String(id))
}

// Handle notification click
function handleNotificationClick(id: number | string) {
  const notification = notifications.value.find((n) => n.id === id)
  if (notification?.action) {
    router.push(notification.action)
  }
}
</script>
