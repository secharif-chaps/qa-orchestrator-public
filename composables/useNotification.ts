import { ref } from 'vue'
import { createApp, h } from 'vue'
import Notification from '~/components/Notification.vue'

interface NotificationOptions {
  title: string
  message: string
  type: 'success' | 'error'
  duration?: number
}

const notifications = ref<NotificationOptions[]>([])

export function useNotification() {
  const show = (options: NotificationOptions) => {
    notifications.value.push(options)
    
    // Créer un conteneur pour la notification
    const container = document.createElement('div')
    document.body.appendChild(container)
    
    // Créer l'instance de la notification
    const app = createApp({
      render: () => h(Notification, {
        ...options,
        onClose: () => {
          notifications.value = notifications.value.filter(n => n !== options)
          app.unmount()
        }
      })
    })
    
    // Monter la notification
    app.mount(container)
  }

  const success = (message: string, title = 'Succès') => {
    show({
      title,
      message,
      type: 'success',
      duration: 5000
    })
  }

  const error = (message: string, title = 'Erreur') => {
    show({
      title,
      message,
      type: 'error',
      duration: 7000
    })
  }

  return {
    notifications,
    show,
    success,
    error
  }
} 