import { useI18n } from 'vue-i18n'
import { useChangeWatchFileStatus } from '~/api/mutations/watchFile'
import { useConfirmModal } from '~/composables/useConfirmModal'
import type { WatchFile, WatchFileStatus } from '~/types/watchFile'
import { WATCH_FILE_STATUS } from '~/types/watchFile'

interface UseWatchFileStatusModalOptions {
  onSuccess?: (newStatus: WatchFileStatus) => void
  onRevert?: () => void
}

export function useWatchFileStatusModal(
  options: UseWatchFileStatusModalOptions,
  watchFile: WatchFile,
) {
  const { t } = useI18n()
  const { showConfirmModal } = useConfirmModal()

  const { changeStatus, isLoading, statusValue } = useChangeWatchFileStatus(watchFile)

  const showStatusModal = (watchFile: WatchFile, newStatus: WatchFileStatus) => {
    switch (newStatus) {
      case WATCH_FILE_STATUS.ENABLED:
        showEnabledModal(watchFile)
        break
      case WATCH_FILE_STATUS.DRAFT:
        showDraftModal(watchFile)
        break
      case WATCH_FILE_STATUS.ARCHIVED:
        showArchivedModal(watchFile)
        break
    }
  }

  const showRestoreModal = async (watchFile: WatchFile) => {
    showConfirmModal({
      title: t('watch_files.status_change.restore.confirm_title'),
      titleIcon: 'fa-rotate-left',
      message: t('watch_files.header_section.deactivation_modal.description', {
        name: watchFile.name,
      }),
      infoSection: {
        items: [
          t('common.dialog.info_item_configuration_unlocked'),
          t('common.dialog.info_item_data_preserved'),
        ],
      },
      confirmLabel: t('watch_files.status_change.restore.confirm_button'),
      onConfirm: () => changeStatus({ id: watchFile.id, status: WATCH_FILE_STATUS.DRAFT }),
    })
  }

  const showEnabledModal = (watchFile: WatchFile) => {
    showConfirmModal({
      title: t('watch_files.header_section.activation_modal.title'),
      titleIcon: 'fa-play',
      message: t('watch_files.header_section.activation_modal.description', {
        name: watchFile.name,
      }),
      infoSection: {
        items: [
          t('common.dialog.info_item_source_monitoring'),
          t('common.dialog.info_item_real_time_collect'),
          t('common.dialog.info_item_ai_validation'),
        ],
      },
      warningSection: {
        message: t('watch_files.header_section.activation.modal.warning_message'),
      },
      confirmLabel: t('watch_files.header_section.activation_modal.confirm'),
      type: 'warning',
      onConfirm: () => {
        changeStatus({ id: watchFile.id, status: WATCH_FILE_STATUS.ENABLED })
      },
      onCancel: () => {
        options.onRevert?.()
      },
    })
  }

  const showDraftModal = (watchFile: WatchFile) => {
    const infoSectionItems = [
      t('common.dialog.info_item_configuration_unlocked'),
      t('common.dialog.info_item_data_preserved'),
    ]
    if (watchFile.status === WATCH_FILE_STATUS.ENABLED) {
      infoSectionItems.unshift(t('common.dialog.info_item_collect_stop'))
    }

    showConfirmModal({
      title: t('watch_files.header_section.deactivation_modal.title'),
      titleIcon: 'fa-file-lines',
      message: t('watch_files.header_section.deactivation_modal.description', {
        name: watchFile.name,
      }),
      infoSection: {
        items: infoSectionItems,
      },
      confirmLabel: t('watch_files.header_section.deactivation_modal.confirm'),
      type: 'warning',
      onConfirm: () => {
        changeStatus({ id: watchFile.id, status: WATCH_FILE_STATUS.DRAFT })
      },
      onCancel: () => {
        options.onRevert?.()
      },
    })
  }

  const showArchivedModal = (watchFile: WatchFile) => {
    const infoSectionItems = [
      t('common.dialog.info_item_archive_default_hidden'),
      t('common.dialog.info_item_archive_always_accessible'),
      t('common.dialog.info_item_archive_restore_possible'),
    ]
    if (watchFile.status === WATCH_FILE_STATUS.ENABLED) {
      infoSectionItems.unshift(t('common.dialog.info_item_collect_stop'))
    }

    showConfirmModal({
      title: t('watch_files.status_change.archive.confirm_title'),
      titleIcon: 'fa-box-archive',
      message: t('watch_files.status_change.archive.confirm_message', {
        name: watchFile.name,
      }),
      infoSection: {
        items: infoSectionItems,
      },
      confirmLabel: t('watch_files.status_change.archive.confirm_button'),
      onConfirm: () => {
        changeStatus({ id: watchFile.id, status: WATCH_FILE_STATUS.ARCHIVED })
      },
      onCancel: () => {
        options.onRevert?.()
      },
    })
  }

  return {
    showStatusModal,
    showRestoreModal,
    statusValue,
    isLoading,
  }
}
