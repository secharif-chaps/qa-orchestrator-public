import { defineStore } from 'pinia';

export const useUserStore = defineStore('user', () => {
  // UI state management only - no server state or API calls
  // This store is now empty as user search functionality has been moved to queries
  // Components should use useQuery with searchUsersQuery instead

  return {
    // No UI state needed for user search functionality
  };
});
