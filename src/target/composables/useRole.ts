import { WATCH_FILE_USER_ROLE, type WatchFileUserRole } from '@target/types/watchFileUser'
import { useI18n } from 'vue-i18n'

export function useRole() {
  const { t } = useI18n()
  function roleLabel(role: WatchFileUserRole): string {
    const roleOptions: Record<WatchFileUserRole, string> = {
      [WATCH_FILE_USER_ROLE.VIEWER]: t('watch_files.shareDialog.role.viewer'),
      [WATCH_FILE_USER_ROLE.EDITOR]: t('watch_files.shareDialog.role.editor'),
      [WATCH_FILE_USER_ROLE.OWNER]: t('watch_files.shareDialog.role.owner'),
    }

    return roleOptions[role] || role
  }

  return { roleLabel }
}
