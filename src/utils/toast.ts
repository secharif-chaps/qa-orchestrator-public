// Simple toast notification utility
// This is a basic implementation - you can replace with a more sophisticated toast library later

type ToastType = 'success' | 'error' | 'warning' | 'info'

interface ToastOptions {
  duration?: number
  position?: 'top-right' | 'top-left' | 'bottom-right' | 'bottom-left'
}

const defaultOptions: Required<ToastOptions> = {
  duration: 5000,
  position: 'top-right'
}

const createToastElement = (message: string, type: ToastType, options: Required<ToastOptions>): HTMLElement => {
  const toast = document.createElement('div')
  toast.className = `
    fixed z-50 max-w-sm w-full p-4 rounded-lg shadow-lg transition-all duration-300 transform translate-x-full
    ${getToastStyles(type)}
    ${getPositionStyles(options.position)}
  `.trim()

  const icon = getToastIcon(type)
  
  toast.innerHTML = `
    <div class="flex items-center gap-3">
      <div class="flex-shrink-0">
        <i class="${icon}"></i>
      </div>
      <div class="flex-1 text-sm font-medium">
        ${message}
      </div>
      <button class="flex-shrink-0 ml-2 text-current opacity-70 hover:opacity-100 transition-opacity">
        <i class="fa fa-times"></i>
      </button>
    </div>
  `

  // Add close functionality
  const closeButton = toast.querySelector('button')
  if (closeButton) {
    closeButton.addEventListener('click', () => {
      hideToast(toast)
    })
  }

  return toast
}

const getToastStyles = (type: ToastType): string => {
  const styles = {
    success: 'bg-green-50 border border-green-200 text-green-800',
    error: 'bg-red-50 border border-red-200 text-red-800',
    warning: 'bg-yellow-50 border border-yellow-200 text-yellow-800',
    info: 'bg-blue-50 border border-blue-200 text-blue-800'
  }
  return styles[type]
}

const getToastIcon = (type: ToastType): string => {
  const icons = {
    success: 'fa fa-check-circle',
    error: 'fa fa-exclamation-circle',
    warning: 'fa fa-exclamation-triangle',
    info: 'fa fa-info-circle'
  }
  return icons[type]
}

const getPositionStyles = (position: string): string => {
  const positions = {
    'top-right': 'top-4 right-4',
    'top-left': 'top-4 left-4',
    'bottom-right': 'bottom-4 right-4',
    'bottom-left': 'bottom-4 left-4'
  }
  return positions[position as keyof typeof positions] || positions['top-right']
}

const showToast = (toast: HTMLElement): void => {
  document.body.appendChild(toast)
  
  // Trigger animation
  setTimeout(() => {
    toast.classList.remove('translate-x-full')
    toast.classList.add('translate-x-0')
  }, 10)
}

const hideToast = (toast: HTMLElement): void => {
  toast.classList.remove('translate-x-0')
  toast.classList.add('translate-x-full')
  
  setTimeout(() => {
    if (toast.parentNode) {
      toast.parentNode.removeChild(toast)
    }
  }, 300)
}

const createToast = (message: string, type: ToastType, options: ToastOptions = {}): void => {
  const finalOptions = { ...defaultOptions, ...options }
  const toast = createToastElement(message, type, finalOptions)
  
  showToast(toast)
  
  // Auto-hide after duration
  setTimeout(() => {
    hideToast(toast)
  }, finalOptions.duration)
}

export const toast = {
  success: (message: string, options?: ToastOptions) => createToast(message, 'success', options),
  error: (message: string, options?: ToastOptions) => createToast(message, 'error', options),
  warning: (message: string, options?: ToastOptions) => createToast(message, 'warning', options),
  info: (message: string, options?: ToastOptions) => createToast(message, 'info', options),
}