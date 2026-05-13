import { type ButtonType, type Intent } from '@owlint/feathers-vue'
import { isRef, ref, type Ref } from 'vue'
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
  type?: Intent
  onConfirm?: () => void | Promise<void>
  onCancel?: () => void
  titleIcon?: string
  /** Icon shown on the confirm button. Falls back to `titleIcon` if omitted. */
  confirmIcon?: string
  /** Vuellar Button variant for the confirm button. Defaults to `'primary'`. */
  confirmVariant?: ButtonType
  /**
   * Reactive loading flag. When true, the confirm button shows a spinner and
   * cancel is disabled. Pass either a `Ref<boolean>` (e.g. a Pinia Colada
   * `isLoading`), a getter, or a plain boolean.
   */
  loading?: Ref<boolean> | (() => boolean) | boolean
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
  type: Intent
  onConfirm: (() => void | Promise<void>) | null
  onCancel: (() => void) | null
  titleIcon?: string
  confirmIcon?: string
  confirmVariant: ButtonType
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
  confirmIcon: undefined,
  confirmVariant: 'primary',
  infoSection: null,
  warningSection: null,
  additionalText: null,
})

// `loading` is normalized to a getter and held outside `modalState`. A `ref()`
// wrapping a `Ref<boolean>` would auto-unwrap and lose the reactivity link
// with the caller's mutation `isLoading`.
const loadingGetter = ref<() => boolean>(() => false)

const toLoadingGetter = (
  loading: Ref<boolean> | (() => boolean) | boolean | undefined,
): (() => boolean) => {
  if (loading === undefined) return () => false
  if (typeof loading === 'boolean') return () => loading
  if (isRef(loading)) return () => loading.value
  return loading
}

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
      confirmIcon: options.confirmIcon,
      confirmVariant: options.confirmVariant || 'primary',
      infoSection: options.infoSection || null,
      warningSection: options.warningSection || null,
      additionalText: options.additionalText || null,
    }
    loadingGetter.value = toLoadingGetter(options.loading)
  }

  const handleConfirm = async () => {
    if (modalState.value.onConfirm) {
      try {
        await modalState.value.onConfirm()
      } catch (err) {
        // Keep the modal open so the user can retry; surface the error for diagnostics.
        console.error('[useConfirmModal] onConfirm error:', err)
        return
      }
    }
    modalState.value.isOpen = false
    loadingGetter.value = () => false
  }

  const handleCancel = () => {
    if (modalState.value.onCancel) {
      modalState.value.onCancel()
    }
    modalState.value.isOpen = false
    loadingGetter.value = () => false
  }

  return {
    modalState,
    loadingGetter,
    showConfirmModal,
    handleConfirm,
    handleCancel,
  }
}
