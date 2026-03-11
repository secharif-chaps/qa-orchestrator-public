import { COLOR } from '@owlint/feathers-vue'
import { ref } from 'vue'

interface Toast {
  id: string
  title: string
  description: string | undefined
  icon: 'fa-check-circle' | 'fa-xmark' | 'fa-circle-info' | 'fa-circle-exclamation'
  canExit: boolean
  color: COLOR
}

const toasts = ref<Toast[]>([])

const addToast = (
  title: string,
  description: string | undefined = undefined,
  icon: Toast['icon'] = 'fa-check-circle',
  color: Toast['color'] = COLOR.sage,
  canExit: boolean = false,
) => {
  const id = String(Date.now())
  toasts.value.push({
    id,
    title,
    description,
    icon,
    color,
    canExit,
  })
  setTimeout(() => {
    removeToast(id)
  }, 3000)
}

const removeToast = (id: string) => {
  const index = toasts.value.findIndex((toast) => toast.id === id)
  if (index !== -1) {
    toasts.value.splice(index, 1)
  }
}

export const useToast = () => {
  return {
    toasts,
    success: (title: string, description?: string) =>
      addToast(title, description, 'fa-check-circle', COLOR.sage),
    error: (title: string, description?: string) =>
      addToast(title, description, 'fa-xmark', COLOR.cherry),
    info: (title: string, description?: string) =>
      addToast(title, description, 'fa-circle-info', COLOR.cyan),
    warning: (title: string, description?: string) =>
      addToast(title, description, 'fa-circle-exclamation', COLOR.yellow),
  }
}
