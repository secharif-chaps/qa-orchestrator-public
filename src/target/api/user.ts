import { useApi } from '~/composables/useApi';
import type { JsonLdCollection } from '~/types/jsonld';
import type { User } from '~/types/user';

export const searchUsers = async (
  query: string,
  excludeWatchFileSharedUsers: string | null = null,
) => {
  const response = await useApi().get<JsonLdCollection<User>>('/users', {
    query: {
      search: query,
      excludeCurrentUser: true,
      excludeWatchFileSharedUsers: excludeWatchFileSharedUsers,
    },
  });

  return response.data;
};
