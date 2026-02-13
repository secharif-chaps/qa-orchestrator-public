import { defineStore } from 'pinia';

export const useWatchFileUserStore = defineStore('watchFileUser', () => {
  // UI state management only - no server state or API calls
  // This store is now empty as watchFileUser functionality has been moved to queries and mutations
  // Components should use:
  // - useQuery with getWatchFileUsersQuery for data fetching
  // - useAddWatchFileUsers, useRemoveWatchFileUser, useUpdateWatchFileUserRole for mutations

  return {
    // No UI state needed for watchFileUser functionality
  };
});
