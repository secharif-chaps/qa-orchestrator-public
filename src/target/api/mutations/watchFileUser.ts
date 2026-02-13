import { defineMutation, useMutation, useQueryCache } from '@pinia/colada';
import { useI18n } from 'vue-i18n';
import { WATCH_FILE_USER_QUERY_KEYS } from '~/api/queries/watchFileUser';
import {
    addWatchFileUsers,
    removeWatchFileUser,
    updateWatchFileUserRole,
} from '~/api/watchFileUser';
import { useRole } from '~/composables/useRole';
import { useToast } from '~/composables/useToast';
import type { User } from '~/types/user';
import type { WatchFileUser, WatchFileUserRole } from '~/types/watchFileUser';

interface CallbackMutations<T> {
  onSuccess?: (data: T) => void;
  onError?: (error: Error) => void;
}

export const useAddWatchFileUsers = (
  options?: CallbackMutations<WatchFileUser[]>,
) => {
  const queryCache = useQueryCache();
  const toast = useToast();
  const { t } = useI18n();

  const defaultErrorMessage = {
    title: t('watch_files.toast.error.addUsers'),
  };

  const { mutate, ...mutation } = useMutation({
    mutation: ({
      watchFileId,
      users,
      role,
    }: {
      watchFileId: string;
      users: User[];
      role: WatchFileUserRole;
    }) => addWatchFileUsers(watchFileId, users, role, defaultErrorMessage),

    onError(error) {
      options?.onError?.(error);
      console.error('Failed to add watch file users:', error);
    },
    onSuccess(data, { watchFileId }) {
      options?.onSuccess?.(data.member);

      toast.success(
        t('watch_files.toast.success.addUsers', {
          count: data.member.length,
        }),
      );

      queryCache.invalidateQueries({
        key: WATCH_FILE_USER_QUERY_KEYS.byWatchFile(watchFileId),
      });
    },
  });

  return { ...mutation, addUsers: mutate };
};

export const useRemoveWatchFileUser = defineMutation(() => {
  const queryCache = useQueryCache();
  const toast = useToast();
  const { t } = useI18n();

  const defaultErrorMessage = {
    title: t('watch_files.toast.error.removeUser'),
  };

  const { mutate, ...mutation } = useMutation({
    mutation: ({
      watchFileId,
      watchFileUserId,
    }: {
      watchFileId: string;
      watchFileUserId: string;
      displayName: string;
    }) =>
      removeWatchFileUser(watchFileId, watchFileUserId, defaultErrorMessage),

    onSuccess(_, { watchFileId, displayName }) {
      toast.success(
        t('watch_files.toast.success.removeUser', {
          user: displayName,
        }),
      );

      queryCache.invalidateQueries({
        key: WATCH_FILE_USER_QUERY_KEYS.byWatchFile(watchFileId),
      });
    },
    onError(error) {
      console.error('Failed to remove watch file user:', error);
    },
  });

  return { ...mutation, removeUser: mutate };
});

export const useUpdateWatchFileUserRole = (
  options?: CallbackMutations<WatchFileUser[]>,
) => {
  const queryCache = useQueryCache();
  const toast = useToast();
  const { t } = useI18n();
  const { roleLabel } = useRole();

  const { mutate, ...mutation } = useMutation({
    mutation: ({
      watchFileId,
      user,
      role,
    }: {
      watchFileId: string;
      user: User;
      role: WatchFileUserRole;
    }) => {
      const defaultErrorMessage = {
        title: t('watch_files.toast.error.changeRole', {
          user: user.displayName,
          role: roleLabel(role),
        }),
      };
      return updateWatchFileUserRole(
        watchFileId,
        user,
        role,
        defaultErrorMessage,
      );
    },

    onError(error) {
      options?.onError?.(error);
      console.error('Failed to update watch file user role:', error);
    },
    onSuccess(data, { watchFileId, user, role }) {
      options?.onSuccess?.(data.member);

      toast.success(
        t('watch_files.toast.success.changeRole', {
          user: user.displayName,
          role: roleLabel(role),
        }),
      );

      queryCache.invalidateQueries({
        key: WATCH_FILE_USER_QUERY_KEYS.byWatchFile(watchFileId),
      });
    },
  });

  return { ...mutation, updateRole: mutate };
};
