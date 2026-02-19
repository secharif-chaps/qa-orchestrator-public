import { defineQueryOptions } from '@pinia/colada';
import { getWatchFileUsers } from '@target/api/watchFileUser';

export const WATCH_FILE_USER_QUERY_KEYS = {
  root: ['watchFileUsers'] as const,
  byWatchFile: (watchFileId: string) =>
    [...WATCH_FILE_USER_QUERY_KEYS.root, 'byWatchFile', watchFileId] as const,
}

export const getWatchFileUsersQuery = defineQueryOptions(
  ({ watchFileId, isOpen }: { watchFileId: string; isOpen: boolean }) => ({
    key: WATCH_FILE_USER_QUERY_KEYS.byWatchFile(watchFileId),
    query: () => getWatchFileUsers(watchFileId),
    enabled: !!watchFileId && isOpen,
  }),
)
