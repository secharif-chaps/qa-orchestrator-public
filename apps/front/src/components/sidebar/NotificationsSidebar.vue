<template>
  <div class="flex h-[calc(100vh-140px)] flex-col px-6">
    <!-- Header -->
    <SidebarHeader :title="$t('common.sidebar.notifications.title')">
      <Button
        v-if="unreadCount"
        variant="tertiary"
        size="sm"
        :label="$t('common.sidebar.notifications.markAllRead')"
        @click="markAllAsRead"
      />
    </SidebarHeader>

    <!-- Notifications List -->
    <div class="flex-1 overflow-y-auto">
      <!-- Loading State -->
      <div v-if="isLoading" class="flex items-center justify-center py-8">
        <i class="fa fa-spinner fa-spin text-sage-400"></i>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="flex flex-col items-center justify-center gap-3 px-4 py-8">
        <Badge variant="secondary" icon="fa fa-exclamation-triangle" size="lg" />
        <p class="text-sage-800 dark:text-sage-400 text-center text-sm">
          {{ $t('common.sidebar.notifications.errorLoading') }}
        </p>
      </div>

      <!-- Notifications -->
      <div v-else-if="notifications.length > 0" class="divide-sage-800 divide-y">
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
      <div v-else class="flex flex-col items-center justify-center gap-4 px-4 py-12">
        <Badge variant="secondary" icon="fa fa-bell" size="lg" />
        <div class="text-center">
          <h3 class="text-sage-900 mb-2 text-sm font-medium">
            {{ $t('common.sidebar.notifications.noNotifications') }}
          </h3>
          <p class="text-sage-800 dark:text-sage-400 text-xs">
            {{ $t('common.sidebar.notifications.upToDate') }}
          </p>
        </div>
      </div>
    </div>

    <!-- Footer Actions -->
    <!--
      Temporarily disabled: "View all notifications" button causes 404 error
      TODO: Re-enable once /notifications page is implemented
    -->
    <!-- <div class="border-t border-sage-800 px-4 py-3 flex justify-center">
      <Button
        variant="tertiary"
        size="sm"
        :label="$t('common.sidebar.notifications.viewAll')"
        icon-right="fa fa-arrow-right"
        @click="$router.push('/notifications')"
      />
    </div> -->
  </div>
</template>

<script setup lang="ts">
import { organizationActivitiesQuery } from '@/queries/organization'
import { formatRelativeTime } from '@/utils/time'
import { Badge, Button } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import NotificationItem from '../ui/NotificationItem.vue'
import SidebarHeader from './SidebarHeader.vue'

// Vuellar Badge colors
type BadgeColor = 'sage' | 'almond' | 'pink' | 'indigo' | 'yellow' | 'cherry' | 'cyan'

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

// Fetch organization activities
const { data: activitiesData, isLoading, error } = useQuery(() => organizationActivitiesQuery())

// Transform activities into notifications (limit to 20 most recent)
const notifications = computed<Notification[]>(() => {
  if (!activitiesData.value) return []

  return activitiesData.value.slice(0, 20).map((activity, index) => {
    const isCompany = activity.type === 'company'
    const notificationId = `${activity.type}-${activity.name}-${index}`

    return {
      id: notificationId,
      title: isCompany
        ? t('common.sidebar.notifications.companyCreated')
        : t('common.sidebar.notifications.folderCreated'),
      message: `${activity.owner} ${t('common.sidebar.notifications.activityMessage')} ${activity.name}`,
      time: formatRelativeTime(activity.created_at),
      category: isCompany
        ? t('common.sidebar.notifications.categoryCompany')
        : t('common.sidebar.notifications.categoryFolder'),
      read: readNotifications.value.has(notificationId),
      icon: {
        icon: isCompany ? 'fa fa-building' : 'fa fa-folder',
        color: isCompany ? 'pink' : 'sage',
      },
      action: undefined,
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
