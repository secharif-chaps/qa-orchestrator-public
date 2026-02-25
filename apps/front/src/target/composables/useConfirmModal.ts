import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

interface InfoSection {
  title?: string
  items: string[]
  icon?: string
}

interface WarningSection {
  title?: string
  message: string
  icon?: string
}

interface ConfirmModalOptions {
  title: string
  message: string
  confirmLabel?: string
  cancelLabel?: string
  type?: 'danger' | 'warning' | 'info'
  onConfirm?: () => void
  onCancel?: () => void
  titleIcon?: string
  infoSection?: InfoSection
  warningSection?: WarningSection
  additionalText?: string
}

interface ConfirmModalState {
  isOpen: boolean
  title: string
  message: string
  confirmLabel: string
  cancelLabel: string
  type: 'danger' | 'warning' | 'info'
  onConfirm: (() => void) | null
  onCancel: (() => void) | null
  titleIcon?: string
  infoSection: InfoSection | null
  warningSection: WarningSection | null
  additionalText: string | null
}

const modalState = ref<ConfirmModalState>({
  isOpen: false,
  title: '',
  message: '',
  confirmLabel: '',
  cancelLabel: '',
  type: 'info',
  onConfirm: null,
  onCancel: null,
  titleIcon: undefined,
  infoSection: null,
  warningSection: null,
  additionalText: null,
})

export function useConfirmModal() {
  const { t } = useI18n()

  const showConfirmModal = (options: ConfirmModalOptions) => {
    modalState.value = {
      isOpen: true,
      title: options.title,
      message: options.message,
      confirmLabel: options.confirmLabel || t('common.button.confirm'),
      cancelLabel: options.cancelLabel || t('common.button.cancel'),
      type: options.type || 'info',
      onConfirm: options.onConfirm || null,
      onCancel: options.onCancel || null,
      titleIcon: options.titleIcon,
      infoSection: options.infoSection || null,
      warningSection: options.warningSection || null,
      additionalText: options.additionalText || null,
    }
  }

  const handleConfirm = () => {
    if (modalState.value.onConfirm) {
      modalState.value.onConfirm()
    }
    modalState.value.isOpen = false
  }

  const handleCancel = () => {
    if (modalState.value.onCancel) {
      modalState.value.onCancel()
    }
    modalState.value.isOpen = false
  }

  return {
    modalState,
    showConfirmModal,
    handleConfirm,
    handleCancel,
  }
}
