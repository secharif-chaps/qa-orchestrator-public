import { defineStore } from 'pinia'
import { ref } from 'vue'

const STORAGE_KEY = 'notifications-last-seen-at'

export const useNotificationsStore = defineStore('notifications', () => {
  const stored = localStorage.getItem(STORAGE_KEY)
  const lastSeenAt = ref<number>(stored ? Number(stored) : 0)

  const markAllSeen = () => {
    lastSeenAt.value = Date.now()
    localStorage.setItem(STORAGE_KEY, String(lastSeenAt.value))
  }

  const isUnread = (createdAt: string | Date): boolean => {
    const timestamp = typeof createdAt === 'string' ? Date.parse(createdAt) : createdAt.getTime()
    return timestamp > lastSeenAt.value
  }

  return {
    lastSeenAt,
    markAllSeen,
    isUnread,
  }
})
